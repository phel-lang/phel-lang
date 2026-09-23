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
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\TagResolver;

use function array_all;
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
 * building the ordinary call. The params become `let` bindings under fresh
 * PHP names ({@see ParamRenamer}), so nothing under a source name is left in
 * the caller's frame, and extra arguments must be on the same allowlist as
 * the body, since the closure captured the enclosing locals before they ran.
 *
 * The bindings land in the caller's PHP frame, so it lowers only in return
 * position outside a `try`, where that frame ends at once. The `let` marks
 * where the spliced body starts ({@see LetNode::withSplicedFnBodyAfter()}),
 * so param type inference of the enclosing fn skips it as it skips a fn.
 *
 * It is direct linking, so it runs only at optimization level 2 and never
 * on a `:redef` `update`, like {@see CallInliner}.
 *
 * Shapes that keep the runtime call: a call in expression or statement
 * position or in a `try`, a fn
 * value, several arities, a rest param, a named fn, a param count the call
 * does not fill, a tagged param or any return type, declared or inferred
 * (the closure declares it, and PHP enforces and coerces it), `recur` aimed at the fn, and a body with any node outside
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

        // The bindings land in the caller's PHP frame, so only where that
        // frame ends right after: a `return` outside any `try`, whose `catch`
        // or `finally` would still run in it. In expression position they
        // would stay visible to the rest of the frame (`get_defined_vars`)
        // and keep the target alive; in statement position the result is
        // discarded anyway.
        if (!$env->isContext(NodeEnvironment::CONTEXT_RETURN) || $env->isWithinTry() || count($args) < 3) {
            return null;
        }

        $fn = $args[2];
        $extraArgs = array_slice($args, 3);
        if (!$fn instanceof FnNode || !$this->isSpliceable($fn, $extraArgs)) {
            return null;
        }

        return $this->lower($args[0], $args[1], $fn, $extraArgs, $env, $analyzer, $location);
    }

    /**
     * @param list<AbstractNode> $extraArgs
     */
    private function isSpliceable(FnNode $fn, array $extraArgs): bool
    {
        if ($fn->isVariadic()
            || $fn->getRecurs()
            || $fn->getName() instanceof Symbol
            || count($fn->getParams()) !== count($extraArgs) + 1
            || $fn->getReturnType() !== null
        ) {
            return false;
        }

        foreach ($fn->getParams() as $param) {
            if (TagResolver::fromMeta($param->getMeta()) !== null) {
                return false;
            }
        }

        // The closure captured the enclosing locals before the extra
        // arguments ran; spliced, the body reads them after. That only
        // differs when an argument can change one, which nothing on the
        // allowlist can.
        return $this->spliceableBody->isSpliceable($fn->getBody())
            && array_all($extraArgs, $this->spliceableBody->isSpliceable(...));
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
    ): ?LetNode {
        // Each param gets a fresh PHP name, as a `let` binding would, so no
        // variable under its source name is left in the caller's frame.
        $params = $fn->getParams();
        $fresh = [];
        foreach ($params as $param) {
            $fresh[$param->getName()] = Symbol::gen($param->getName() . '_');
        }

        $body = new ParamRenamer($fresh)->rename($fn->getBody());
        if (!$body instanceof AbstractNode) {
            return null;
        }

        // The body was analysed as the fn's return value; its leading forms
        // stay statements, and only the value moves into the `assoc`.
        $statements = $body instanceof DoNode ? $body->getStmts() : [];

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

        $read = new CallNode(
            $expressionEnv,
            $this->coreFn('get', $expressionEnv, $analyzer),
            [new LocalVarNode($expressionEnv, $targetSym, $location), new LocalVarNode($expressionEnv, $keySym, $location)],
            $location,
        );
        $bindings[] = new BindingNode($env, $params[0], $fresh[$params[0]->getName()], $read, $location);
        foreach ($extraSyms as $i => $extraSym) {
            $param = $params[$i + 1];
            $bindings[] = new BindingNode($env, $param, $fresh[$param->getName()], new LocalVarNode($expressionEnv, $extraSym, $location), $location);
        }

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

        return new LetNode($env, $bindings, new DoNode($bodyEnv, $statements, $write, $location), false, $location)
            ->withSplicedFnBodyAfter(2 + count($extraArgs));
    }

    private function coreFn(string $name, NodeEnvironmentInterface $env, AnalyzerInterface $analyzer): AbstractNode
    {
        return $analyzer->analyze(Symbol::createForNamespace(self::CORE_NS, $name), $env);
    }
}
