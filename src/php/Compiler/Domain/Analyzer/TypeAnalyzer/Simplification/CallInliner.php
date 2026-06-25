<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\ConstantFolder;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

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
 *  1. **Argument evaluation is preserved.** A *pure* argument
 *     ({@see SymbolicPurityDetector}) can be dropped (unused param),
 *     duplicated (param used more than once) or reordered with no
 *     observable effect, so it is substituted into the body directly
 *     (which also exposes it to constant folding). An *impure* argument,
 *     or a pure one used more than once, is bound to a fresh gensym
 *     `let` at the call site and the body references that binding — so
 *     each argument still evaluates exactly once, left to right
 *     (issue #2215).
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
        private LetSimplifier $letSimplifier = new LetSimplifier(),
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

        // The side-table holds single-arity (`FnNode`) and multi-arity
        // (`MultiFnNode`) defs; `arityFor` resolves the fixed arity whose
        // parameter count matches the call (#2218), or `null` when the
        // callee is unknown, not a `defn`, or only matches a variadic
        // arity.
        $fnNode = $analyzer->getDefFnNode($f->getNamespace(), $f->getName())?->arityFor(count($args));
        if (!$fnNode instanceof FnNode) {
            return null;
        }

        if ($fnNode->getRecurs()) {
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

        $body = $fnNode->getBody();
        if (!$body instanceof DoNode || $body->getStmts() !== []) {
            return null;
        }

        // A `^:pure` callee asserts its own body is side-effect-free, so we
        // trust the annotation instead of structurally proving the body
        // pure. The rebaser still aborts on any unsupported node type.
        $ret = $body->getRet();
        if (!$this->purity->isPureAnnotated($f->getMeta())
            && !$this->purity->isPure($ret)
        ) {
            return null;
        }

        // Pure args substitute straight into the body (folding-friendly);
        // impure or pure-but-multi-use args bind to a fresh gensym `let`
        // so they evaluate exactly once, left to right.
        //
        // Each binding's shadow is also registered as a local on the env
        // threaded into the body rebase: those shadows are assigned ahead
        // of the body, so any nested node that emits a closure (a `let`
        // in expression context, an `or`/`and`/`cond` IIFE) must capture
        // them in its `use(...)` clause. Leaving them off the env would
        // make the closure capture the call-site locals only and read the
        // shadow as an undefined variable (issue #2622).
        $bindings = [];
        $paramMap = [];
        $scopeEnv = $env;
        foreach ($params as $i => $param) {
            $arg = $args[$i];
            $name = $param->getName();

            if ($this->shouldBind($arg, $name, $ret)) {
                $shadow = Symbol::gen($name . '_')->copyLocationFrom($param);
                $bindings[] = new BindingNode($env, $param, $shadow, $arg, $callLocation);
                $paramMap[$name] = new LocalVarNode($env, $shadow, $callLocation);
                $scopeEnv = $scopeEnv->withMergedLocals([$shadow]);

                continue;
            }

            $paramMap[$name] = $arg;
        }

        // The body of a binding-wrapping `let` emits under the let body's
        // context (return when the call site is an expression, so the
        // generated IIFE returns the value); the unwrapped splice keeps
        // the call site's own context byte-for-byte.
        $bodyContext = ($bindings !== [] && $env->isContext(NodeEnvironment::CONTEXT_EXPRESSION))
            ? NodeEnvironment::CONTEXT_RETURN
            : $env->getContext();

        // `rebase` can still abort (returning `null`) if the body holds a
        // node type outside the whitelist or a non-parameter local.
        $inlined = $this->rebase($ret, new RebaseContext($scopeEnv, $bodyContext, $callLocation, $paramMap, true));
        if (!$inlined instanceof AbstractNode) {
            return null;
        }

        $folded = $this->fold($inlined);
        if ($bindings === []) {
            return $folded;
        }

        $letNode = new LetNode($env, $bindings, $folded, false, $callLocation);

        return $this->letSimplifier->simplify($letNode);
    }

    /**
     * A pure argument is substituted directly unless it is a non-literal
     * used more than once in the body (then binding it avoids duplicating
     * the computation). An impure argument always binds, so its single
     * effect is preserved even when the param is unused or repeated.
     */
    private function shouldBind(AbstractNode $arg, string $paramName, AbstractNode $body): bool
    {
        if (!$this->purity->isPure($arg)) {
            return true;
        }

        if ($arg instanceof LiteralNode) {
            return false;
        }

        return $this->countUses($paramName, $body) > 1;
    }

    private function countUses(string $name, AbstractNode $node): int
    {
        if ($node instanceof LocalVarNode) {
            return $node->getName()->getName() === $name ? 1 : 0;
        }

        if ($node instanceof CallNode) {
            $count = $this->countUses($name, $node->getFn());
            foreach ($node->getArguments() as $arg) {
                $count += $this->countUses($name, $arg);
            }

            return $count;
        }

        if ($node instanceof IfNode) {
            return $this->countUses($name, $node->getTestExpr())
                + $this->countUses($name, $node->getThenExpr())
                + $this->countUses($name, $node->getElseExpr());
        }

        if ($node instanceof VectorNode) {
            return $this->countUsesInAll($name, $node->getArgs());
        }

        if ($node instanceof SetNode) {
            return $this->countUsesInAll($name, $node->getValues());
        }

        if ($node instanceof MapNode) {
            return $this->countUsesInAll($name, $node->getKeyValues());
        }

        if ($node instanceof LetNode) {
            // A `let` shadow is a gensym, never a parameter name, so summing
            // the inits and the body counts only genuine parameter uses.
            $count = $this->countUses($name, $node->getBodyExpr());
            foreach ($node->getBindings() as $binding) {
                $count += $this->countUses($name, $binding->getInitExpr());
            }

            return $count;
        }

        if ($node instanceof DoNode) {
            $count = $this->countUses($name, $node->getRet());
            foreach ($node->getStmts() as $stmt) {
                $count += $this->countUses($name, $stmt);
            }

            return $count;
        }

        return 0;
    }

    /**
     * @param array<int, AbstractNode> $nodes
     */
    private function countUsesInAll(string $name, array $nodes): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            $count += $this->countUses($name, $node);
        }

        return $count;
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

        if ($node instanceof VectorNode) {
            $args = $this->rebaseElements($node->getArgs(), $ctx);

            return $args === null ? null : new VectorNode($ctx->targetEnv(), $args, $ctx->loc);
        }

        if ($node instanceof SetNode) {
            $values = $this->rebaseElements($node->getValues(), $ctx);

            return $values === null ? null : new SetNode($ctx->targetEnv(), $values, $ctx->loc);
        }

        if ($node instanceof MapNode) {
            $keyValues = $this->rebaseElements($node->getKeyValues(), $ctx);

            return $keyValues === null ? null : new MapNode($ctx->targetEnv(), $keyValues, $ctx->loc);
        }

        if ($node instanceof LetNode) {
            return $this->rebaseLet($node, $ctx);
        }

        if ($node instanceof DoNode) {
            return $this->rebaseDo($node, $ctx);
        }

        return null;
    }

    /**
     * Rebases a `do` body wrapper. `let`/`if`/`fn` bodies are stored as a
     * `DoNode`, so a let-bodied callee always reaches the rebaser through
     * one. Only the statement-free form is spliceable — mirroring the
     * `tryInline` fn-body gate — so a `do` carrying leading statements
     * (which would need their own per-statement context handling) aborts
     * the inline. The wrapper is preserved so downstream passes that
     * expect a `DoNode` body keep working.
     */
    private function rebaseDo(DoNode $node, RebaseContext $ctx): ?AbstractNode
    {
        if ($node->getStmts() !== []) {
            return null;
        }

        $ret = $this->rebase($node->getRet(), $ctx);

        return $ret instanceof AbstractNode
            ? new DoNode($ctx->targetEnv(), [], $ret, $ctx->loc)
            : null;
    }

    /**
     * Rebases a (non-loop) `let` from the callee body onto the call site.
     *
     * Each binding gets a FRESH gensym shadow, so the inlined scope can
     * never collide with a caller local or with another copy of the same
     * `defn` spliced into the same PHP scope. References to the old
     * shadow — in later binding inits and in the body — are remapped
     * through the same substitution map used for parameters, so the walk
     * stays in `inBody` mode and rewrites every reference exactly once.
     *
     * Bindings are processed in order: each init sees the parameters plus
     * the freshly-remapped shadows of the preceding bindings, preserving
     * sequential `let` semantics. A `loop` is never rebased — it owns its
     * `recur` targets, which the inliner must not disturb.
     */
    private function rebaseLet(LetNode $node, RebaseContext $ctx): ?AbstractNode
    {
        if ($node->isLoop()) {
            return null;
        }

        // A `let` in expression context emits as an IIFE whose body must
        // `return`; in any other context the body shares the let's own
        // context. This mirrors the binding-wrapper let built in `tryInline`.
        $bodyContext = $ctx->context === NodeEnvironment::CONTEXT_EXPRESSION
            ? NodeEnvironment::CONTEXT_RETURN
            : $ctx->context;

        // Like the parameter shadows in `tryInline`, each fresh let shadow
        // is added to the env threaded into later inits and the body, so a
        // nested closure (`let`/`or`/`and`/`cond` IIFE) captures it in its
        // `use(...)` clause instead of reading it as undefined (#2622). An
        // init only sees the shadows of the bindings preceding it, matching
        // sequential `let` scoping.
        $paramMap = $ctx->paramMap;
        $scopeEnv = $ctx->env;
        $bindings = [];
        foreach ($node->getBindings() as $binding) {
            // Inits emit as expressions (`$shadow = <init>;`) and may
            // reference params or earlier bindings, so rebase them in
            // expression context with the substitutions accumulated so far.
            $initCtx = new RebaseContext($scopeEnv, NodeEnvironment::CONTEXT_EXPRESSION, $ctx->loc, $paramMap, true);
            $init = $this->rebase($binding->getInitExpr(), $initCtx);
            if (!$init instanceof AbstractNode) {
                return null;
            }

            $shadow = Symbol::gen($binding->getShadow()->getName() . '_')->copyLocationFrom($binding->getShadow());
            $bindings[] = new BindingNode($ctx->targetEnv(), $binding->getSymbol(), $shadow, $init, $ctx->loc);
            $paramMap[$binding->getShadow()->getName()] = new LocalVarNode($ctx->env, $shadow, $ctx->loc);
            $scopeEnv = $scopeEnv->withMergedLocals([$shadow]);
        }

        $bodyCtx = new RebaseContext($scopeEnv, $bodyContext, $ctx->loc, $paramMap, true);
        $body = $this->rebase($node->getBodyExpr(), $bodyCtx);
        if (!$body instanceof AbstractNode) {
            return null;
        }

        return new LetNode($ctx->targetEnv(), $bindings, $body, false, $ctx->loc);
    }

    /**
     * Rebases each element of a collection literal in expression context
     * (collection elements always emit as expressions). Returns `null`
     * as soon as any element falls outside the whitelist.
     *
     * Reader-attached meta is dropped intentionally: {@see SymbolicPurityDetector}
     * only reports a meta-free collection as pure, so a node reaching
     * here never carried any.
     *
     * @param array<int, AbstractNode> $nodes
     *
     * @return array<int, AbstractNode>|null
     */
    /**
     * @param array<int, AbstractNode> $nodes
     *
     * @return list<AbstractNode>|null
     */
    private function rebaseElements(array $nodes, RebaseContext $ctx): ?array
    {
        $subCtx = $ctx->withContext(NodeEnvironment::CONTEXT_EXPRESSION);

        $rebased = [];
        foreach ($nodes as $node) {
            $element = $this->rebase($node, $subCtx);
            if (!$element instanceof AbstractNode) {
                return null;
            }

            $rebased[] = $element;
        }

        return $rebased;
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
