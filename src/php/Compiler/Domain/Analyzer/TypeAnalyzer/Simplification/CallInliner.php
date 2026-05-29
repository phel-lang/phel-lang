<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\ConstantFolder;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;

use function array_key_exists;
use function count;

/**
 * Inlines a call to a single-expression pure `defn` at the call site
 * (issue #2135). Instead of dispatching through the resolved
 * `AbstractFn`, the callee's body is spliced in with each parameter
 * replaced by the corresponding (analysed) argument node, skipping one
 * PHP frame and exposing the body to downstream constant folding.
 *
 * Gated behind `setOptimizationLevel >= 2`: at lower levels every call
 * routes through the normal dispatch path, so the compiled stdlib stays
 * byte-identical.
 *
 * Soundness rests on three restrictions:
 *
 *  1. **Pure arguments only.** Each argument must be pure per
 *     {@see SymbolicPurityDetector}, so dropping (unused param),
 *     duplicating (param used more than once) or reordering it across
 *     the splice has no observable effect.
 *  2. **Pure single-expression body.** The body must be one pure
 *     expression (a `DoNode` with no leading statements), so splicing it
 *     exactly once preserves the callee's semantics and effect count.
 *  3. **Closed node whitelist.** Only node types whose environment can
 *     be safely rebased onto the call site are rewritten; anything else
 *     aborts the inline (returns `null`) and the call falls back to
 *     dispatch.
 *
 * Because the spliced nodes are already analysed (globals resolved,
 * macros expanded), inlining works across namespaces and never
 * re-enters the analyzer, so no re-resolution or re-expansion can occur.
 */
final readonly class CallInliner
{
    public function __construct(
        private SymbolicPurityDetector $purity = new SymbolicPurityDetector(),
        private ConstantFolder $folder = new ConstantFolder(),
    ) {}

    /**
     * @param list<AbstractNode> $args analysed arguments, in expression context
     *
     * @return AbstractNode|null the inlined (and folded) node, or `null`
     *                           when the call must keep dispatching
     */
    public function tryInline(
        GlobalVarNode $f,
        array $args,
        NodeEnvironmentInterface $env,
        AnalyzerInterface $analyzer,
        ?SourceLocation $callLocation = null,
    ): ?AbstractNode {
        if ($analyzer->getOptimizationLevel() < 2) {
            return null;
        }

        if ($this->isMemoisedOrAsync($f->getMeta())) {
            return null;
        }

        // The side-table only holds single-arity `defn` bodies; a `null`
        // here means the callee is unknown, multi-arity, or not a `defn`.
        $fnNode = $analyzer->getDefFnNode($f->getNamespace(), $f->getName());
        if (!$fnNode instanceof FnNode) {
            return null;
        }

        if ($fnNode->isVariadic() || $fnNode->getRecurs()) {
            return null;
        }

        // Stay out of the tail slot of an enclosing `loop`/`recur` frame
        // so we never disturb tail-call semantics.
        if ($env->getCurrentRecurFrame() instanceof RecurFrame
            && $env->isContext(NodeEnvironment::CONTEXT_RETURN)
        ) {
            return null;
        }

        $params = $fnNode->getParams();
        if (count($params) !== count($args)) {
            return null;
        }

        $body = $fnNode->getBody();
        if (!$body instanceof DoNode || $body->getStmts() !== []) {
            return null;
        }

        foreach ($args as $arg) {
            if (!$this->purity->isPure($arg)) {
                return null;
            }
        }

        $ret = $body->getRet();
        if (!$this->purity->isPure($ret)) {
            return null;
        }

        $paramMap = [];
        foreach ($params as $i => $param) {
            $paramMap[$param->getName()] = $args[$i];
        }

        // `rebase` can still abort (returning `null`) if the body holds a
        // node type outside the whitelist or a non-parameter local.
        $inlined = $this->rebase($ret, new RebaseContext($env, $env->getContext(), $callLocation, $paramMap, true));
        if (!$inlined instanceof AbstractNode) {
            return null;
        }

        return $this->fold($inlined);
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    private function isMemoisedOrAsync(PersistentMapInterface $meta): bool
    {
        return (bool) $meta[Keyword::create('memoize')]
            || (bool) $meta[Keyword::create('memoize-lru')]
            || (bool) $meta[Keyword::create('async')];
    }

    /**
     * Rebuilds a whitelisted node onto the call-site environment.
     */
    private function rebase(AbstractNode $node, RebaseContext $ctx): ?AbstractNode
    {
        if ($node instanceof LiteralNode) {
            return new LiteralNode($ctx->targetEnv(), $node->getValue(), $ctx->loc);
        }

        if ($node instanceof GlobalVarNode) {
            return new GlobalVarNode($ctx->targetEnv(), $node->getNamespace(), $node->getName(), $node->getMeta(), $ctx->loc);
        }

        if ($node instanceof PhpVarNode) {
            return new PhpVarNode($ctx->targetEnv(), $node->getName(), $ctx->loc);
        }

        if ($node instanceof LocalVarNode) {
            return $this->rebaseLocalVar($node, $ctx);
        }

        if ($node instanceof CallNode) {
            return $this->rebaseCall($node, $ctx);
        }

        if ($node instanceof IfNode) {
            return $this->rebaseIf($node, $ctx);
        }

        return null;
    }

    /**
     * `$ctx->inBody` distinguishes the two walks that share the rebasing
     * logic: the callee body (where a `LocalVarNode` naming a parameter
     * is replaced by its argument, and any other local aborts the
     * inline) and an argument subtree (where every `LocalVarNode` is a
     * caller-scope local and is kept verbatim). Switching to argument
     * mode on substitution prevents a caller local that happens to share
     * a parameter's name from being substituted a second time.
     */
    private function rebaseLocalVar(LocalVarNode $node, RebaseContext $ctx): ?AbstractNode
    {
        if (!$ctx->inBody) {
            // Caller-scope local inside an argument subtree: keep it.
            return new LocalVarNode($ctx->targetEnv(), $node->getName(), $ctx->loc);
        }

        $name = $node->getName()->getName();
        if (array_key_exists($name, $ctx->paramMap)) {
            return $this->rebase($ctx->paramMap[$name], $ctx->asArgument());
        }

        // A non-parameter local in the callee body would reference a
        // binding we are not introducing here: refuse to inline.
        return null;
    }

    private function rebaseCall(CallNode $node, RebaseContext $ctx): ?AbstractNode
    {
        $subCtx = $ctx->withContext(NodeEnvironment::CONTEXT_EXPRESSION);

        $fn = $this->rebase($node->getFn(), $subCtx);
        if (!$fn instanceof AbstractNode) {
            return null;
        }

        $args = [];
        foreach ($node->getArguments() as $arg) {
            $rebased = $this->rebase($arg, $subCtx);
            if (!$rebased instanceof AbstractNode) {
                return null;
            }

            $args[] = $rebased;
        }

        return new CallNode($ctx->targetEnv(), $fn, $args, $ctx->loc);
    }

    private function rebaseIf(IfNode $node, RebaseContext $ctx): ?AbstractNode
    {
        $test = $this->rebase($node->getTestExpr(), $ctx->withContext(NodeEnvironment::CONTEXT_EXPRESSION));
        $then = $this->rebase($node->getThenExpr(), $ctx);
        $else = $this->rebase($node->getElseExpr(), $ctx);

        if (!$test instanceof AbstractNode || !$then instanceof AbstractNode || !$else instanceof AbstractNode) {
            return null;
        }

        return new IfNode($ctx->targetEnv(), $test, $then, $else, $ctx->loc);
    }

    private function fold(AbstractNode $node): AbstractNode
    {
        if ($node instanceof CallNode) {
            return $this->folder->fold($node) ?? $node;
        }

        if ($node instanceof IfNode) {
            return $this->folder->foldIf($node) ?? $node;
        }

        return $node;
    }
}
