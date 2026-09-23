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
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReturnTypeInferrer;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\TagResolver;

use function array_slice;
use function count;

/**
 * Lowers `(update m k (fn [v x ...] body) x ...)` with a literal one-arity
 * `fn` to the `let` the runtime `update` would have run, with the fn body
 * spliced in (#3322):
 *
 *     (let [ds m, k k, x' x ..., v (phel.core/get ds k), x x' ...]
 *       (phel.core/assoc ds k body))
 *
 * No closure is built and nothing dispatches through `phel.core/update`.
 * Target, key and extra arguments evaluate once each and in source order,
 * then the read, then the body, as they did.
 *
 * It works on the call as analysed: the fn was analysed once, in its own
 * environment, so macros in the body saw the `&env` they always saw, the
 * params stay untyped as a closure's are, and declining costs nothing but
 * building the ordinary call. The params keep their names and become the
 * `let` bindings, so a param named like a local of the enclosing scope
 * declines rather than clobber that PHP variable.
 *
 * A `let` in expression position normally emits as an IIFE, which would cost
 * what the closure cost. The lowered one is flagged to emit its bindings as
 * assignments inside the expression instead ({@see LetNode::isInlineInExpression()}).
 *
 * It is direct linking, so it runs only at optimization level 2 and never
 * on a `:redef` `update`, like {@see CallInliner}.
 *
 * Shapes that keep the runtime call: a call in statement position, a fn
 * value, several arities, a rest param, a named fn, a param count the call
 * does not fill, a tagged param or a return tag (PHP enforces and
 * coerces both), `recur` aimed at the fn, and a body with any node outside
 * the frame-independent allowlist of {@see SpliceableBody}.
 *
 * @internal
 */
final readonly class UpdateLiteralFnLowering
{
    private const string CORE_NS = 'phel.core';

    private const string UPDATE = 'update';

    public function __construct(
        private SpliceableBody $spliceableBody = new SpliceableBody(),
        private ExpressionContextRebuilder $rebuilder = new ExpressionContextRebuilder(),
    ) {}

    /**
     * @param list<AbstractNode> $args analysed arguments of the `update` call
     */
    public function tryLower(
        GlobalVarNode $f,
        array $args,
        NodeEnvironmentInterface $env,
        AnalyzerInterface $analyzer,
        ?SourceLocation $location,
    ): ?AbstractNode {
        if ($f->getNamespace() !== self::CORE_NS || $f->getName()->getName() !== self::UPDATE) {
            return null;
        }

        // Splicing leaves no read of `update` for `with-redefs` or
        // `phel.mock` to intercept, so it is direct linking and takes the
        // same gates as {@see CallInliner}: level 2, and never on `:redef`.
        if ($analyzer->getOptimizationLevel() < 2 || (bool) $f->getMeta()[Keyword::create('redef')]) {
            return null;
        }

        // A discarded result gains nothing, and a typed `->put()` in
        // statement position trips the `#[\NoDiscard]` on it.
        if ($env->isContext(NodeEnvironment::CONTEXT_STATEMENT) || count($args) < 3) {
            return null;
        }

        $fn = $args[2];
        $extraArgs = array_slice($args, 3);
        if (!$fn instanceof FnNode || !$this->isSpliceable($fn, count($extraArgs), $env)) {
            return null;
        }

        return $this->lower($args[0], $args[1], $fn, $extraArgs, $env, $analyzer, $location);
    }

    private function isSpliceable(FnNode $fn, int $extraArgCount, NodeEnvironmentInterface $env): bool
    {
        if ($fn->isVariadic()
            || $fn->getRecurs()
            || $fn->getName() instanceof Symbol
            || count($fn->getParams()) !== $extraArgCount + 1
            || $this->hasDeclaredReturnType($fn)
        ) {
            return false;
        }

        $enclosing = $this->enclosingNames($env);
        foreach ($fn->getParams() as $param) {
            if (isset($enclosing[$param->getName()]) || TagResolver::fromMeta($param->getMeta()) !== null) {
                return false;
            }
        }

        return $this->spliceableBody->isSpliceable($fn->getBody());
    }

    /**
     * A return tag compiles to a PHP return type, which PHP enforces and, for
     * a scalar, coerces. The fn also carries the type the body was inferred
     * to return; that one only restates what the body does, so a type the
     * inferrer reproduces is not a tag and does not decline.
     */
    private function hasDeclaredReturnType(FnNode $fn): bool
    {
        $type = $fn->getReturnType();

        return $type !== null
            && $type !== new ReturnTypeInferrer()->infer($fn->getBody(), $fn->getParams(), $fn->isVariadic());
    }

    /**
     * Every PHP variable name the enclosing scope may hold: each visible
     * local and the shadow it emits as. A param reusing one would overwrite
     * it, where the closure had its own.
     *
     * @return array<string, true>
     */
    private function enclosingNames(NodeEnvironmentInterface $env): array
    {
        $names = [];
        foreach ($env->getLocals() as $local) {
            $names[$local->getName()] = true;
            $shadow = $env->getShadowed($local);
            if ($shadow instanceof Symbol) {
                $names[$shadow->getName()] = true;
            }
        }

        return $names;
    }

    /**
     * @param list<AbstractNode> $extraArgs
     */
    private function lower(
        AbstractNode $target,
        AbstractNode $key,
        FnNode $fn,
        array $extraArgs,
        NodeEnvironmentInterface $env,
        AnalyzerInterface $analyzer,
        ?SourceLocation $location,
    ): LetNode {
        $expressionEnv = $env->withExpressionContext();
        $targetSym = Symbol::gen('ds_');
        $keySym = Symbol::gen('k_');

        $bindings = [
            new BindingNode($env, $targetSym, $targetSym, $target, $location),
            new BindingNode($env, $keySym, $keySym, $key, $location),
        ];

        $extraSyms = [];
        foreach ($extraArgs as $arg) {
            $extraSym = Symbol::gen('x_');
            $extraSyms[] = $extraSym;
            $bindings[] = new BindingNode($env, $extraSym, $extraSym, $arg, $location);
        }

        $params = $fn->getParams();
        $read = new CallNode(
            $expressionEnv,
            $this->coreFn('get', $expressionEnv, $analyzer),
            [new LocalVarNode($expressionEnv, $targetSym, $location), new LocalVarNode($expressionEnv, $keySym, $location)],
            $location,
        );
        $bindings[] = new BindingNode($env, $params[0], $params[0], $read, $location);
        foreach ($extraSyms as $i => $extraSym) {
            $param = $params[$i + 1];
            $bindings[] = new BindingNode($env, $param, $param, new LocalVarNode($expressionEnv, $extraSym, $location), $location);
        }

        // The body was analysed as the fn's return value; its leading forms
        // stay statements, and only the value moves into the `assoc`.
        $body = $fn->getBody();
        $statements = $body instanceof DoNode ? $body->getStmts() : [];
        $value = $body instanceof DoNode ? $body->getRet() : $body;

        $bodyEnv = $env->withReturnContext();
        $write = new CallNode(
            $bodyEnv,
            $this->coreFn('assoc', $expressionEnv, $analyzer),
            [
                new LocalVarNode($expressionEnv, $targetSym, $location),
                new LocalVarNode($expressionEnv, $keySym, $location),
                $this->rebuilder->rebuild($value),
            ],
            $location,
        );

        $let = new LetNode($env, $bindings, new DoNode($bodyEnv, $statements, $write, $location), false, $location);

        return $env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)
            ? $let->withInlineInExpression()
            : $let;
    }

    private function coreFn(string $name, NodeEnvironmentInterface $env, AnalyzerInterface $analyzer): AbstractNode
    {
        return $analyzer->analyze(Symbol::createForNamespace(self::CORE_NS, $name), $env);
    }
}
