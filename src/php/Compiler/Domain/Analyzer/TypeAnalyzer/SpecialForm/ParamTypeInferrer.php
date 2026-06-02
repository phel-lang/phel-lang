<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Lang\Symbol;

use function array_unique;
use function count;

/**
 * Walks a fn body to surface conservative param-type contracts. Results
 * feed two consumers: the static checker (call-site mismatch diagnostics)
 * and `DefSymbol`, which grafts the inferred tag onto each param Symbol's
 * meta so the emitter renders `int $x` / `string $x` in the compiled PHP
 * signature. OPcache JIT specialises on those typed slots.
 *
 * Inference is deliberately narrow. A param earns a tag only when every
 * use across every reached branch agrees on the same primitive. Type
 * guards drop the param so the runtime contract stays permissive:
 *   - `?`-suffixed Phel predicates and `assert-non-nil`/`assert` globals
 *   - PHP `is_*` predicates
 *   - identity comparisons against `nil`/`true`/`false`
 *   - disagreeing observations across branches (e.g. compared to int and
 *     concatenated as string)
 *
 * Call-shape classification (which ops constrain, which guard, which carry
 * cross-fn `:param-tags`) lives in {@see CallTypeExpectationResolver}; this
 * class owns the walk and its mutable observation state.
 */
final class ParamTypeInferrer
{
    /** @var array<string, list<string>> */
    private array $observations = [];

    /** @var array<string, true> */
    private array $params = [];

    /** @var array<string, true> */
    private array $guarded = [];

    private ?string $selfNamespace = null;

    private ?string $selfName = null;

    public function __construct(
        private readonly CallTypeExpectationResolver $resolver = new CallTypeExpectationResolver(),
    ) {}

    /**
     * `$selfNamespace` / `$selfName` short-circuit cross-fn propagation
     * for the def currently being analyzed. The runtime registry may
     * still hold meta from a previous compile/eval of the same name
     * (the analyzer's compile-time meta has been cleared in
     * `addDefinition`, but registry meta persists across compilations
     * of the same singleton); treating any self-referencing call as
     * untagged keeps the new definition from inheriting that stale
     * signal.
     *
     * @param list<Symbol> $params
     *
     * @return array<string, string>
     */
    public function infer(
        AbstractNode $body,
        array $params,
        bool $isVariadic = false,
        ?string $selfNamespace = null,
        ?string $selfName = null,
    ): array {
        $this->observations = [];
        $this->params = [];
        $this->guarded = [];
        $this->selfNamespace = $selfNamespace;
        $this->selfName = $selfName;

        $lastIndex = $isVariadic ? count($params) - 1 : count($params);
        for ($i = 0; $i < $lastIndex; ++$i) {
            // The variadic tail binds a `Vector`, not a scalar; excluding
            // it keeps numeric/string observations from constraining the
            // wrong runtime shape.
            $this->params[$params[$i]->getName()] = true;
        }

        if ($this->params === []) {
            return [];
        }

        $this->walk($body);

        $result = [];
        foreach ($this->observations as $name => $types) {
            if (isset($this->guarded[$name])) {
                continue;
            }

            $unique = array_unique($types);
            if (count($unique) === 1) {
                $result[$name] = $unique[0];
            }
        }

        return $result;
    }

    private function walk(AbstractNode $node, ?string $expected = null): void
    {
        if ($node instanceof FnNode) {
            // Closures own their own params; a `$x` inside is unrelated
            // to the outer fn's `$x`.
            return;
        }

        if ($node instanceof LocalVarNode) {
            if ($expected !== null) {
                $this->constrainArgAsScalar($node, $expected);
            }

            return;
        }

        if ($node instanceof DoNode) {
            foreach ($node->getStmts() as $stmt) {
                $this->walk($stmt);
            }

            $this->walk($node->getRet(), $expected);
            return;
        }

        if ($node instanceof IfNode) {
            $this->walk($node->getTestExpr());
            $this->walk($node->getThenExpr(), $expected);
            $this->walk($node->getElseExpr(), $expected);
            return;
        }

        if ($node instanceof LetNode) {
            foreach ($node->getBindings() as $binding) {
                $this->walk($binding->getInitExpr());
            }

            $this->walk($node->getBodyExpr(), $expected);
            return;
        }

        if ($node instanceof RecurNode) {
            // recur rebinds loop locals positionally; we can't constrain
            // the bound names from the call site without tracking the
            // matching loop frame, so just walk arg expressions for any
            // operator usage they contain.
            foreach ($node->getExpressions() as $expr) {
                $this->walk($expr);
            }

            return;
        }

        if ($node instanceof ThrowNode) {
            return;
        }

        if ($node instanceof CallNode) {
            $this->walkCall($node, $expected);
            return;
        }
    }

    private function walkCall(CallNode $node, ?string $expected = null): void
    {
        $fn = $node->getFn();
        // Recurse into the callee position first: descending captures any
        // operator hidden in a higher-order arg without blocking the
        // fn-position itself from acting as a constraint source.
        $this->walk($fn);

        if ($fn instanceof GlobalVarNode && $this->resolver->isGuardGlobal($fn)) {
            $this->walkArgsAsGuarded($node);
            return;
        }

        if ($fn instanceof PhpVarNode) {
            $this->walkPhpCall($node, $fn, $expected);
            return;
        }

        if ($fn instanceof GlobalVarNode) {
            $this->walkGlobalCall($node, $fn, $expected);
            return;
        }

        // Unknown call position (call against a literal, vector, etc.):
        // walk args plainly so any nested operator still observes its
        // locals.
        $this->walkArgsExpecting($node, null);
    }

    private function walkPhpCall(CallNode $node, PhpVarNode $fn, ?string $expected): void
    {
        $op = $fn->getName();

        if ($this->resolver->isGuardPhpFn($op)) {
            $this->walkArgsAsGuarded($node);
            return;
        }

        if ($this->resolver->isStringConcatOp($op)) {
            $this->walkArgsExpecting($node, 'string');
            return;
        }

        if ($this->resolver->isNumericOp($op)) {
            $this->walkNumericCall($node, $expected);
            return;
        }

        if ($this->resolver->isIdentityOp($op)) {
            $this->walkIdentityCall($node);
            return;
        }

        if ($this->resolver->isOrderingOp($op)) {
            $this->walkOrderingCall($node);
            return;
        }

        $signature = $this->resolver->phpFnSignature($op);
        if ($signature !== null) {
            $this->walkArgsBySignature($node, $signature);
            return;
        }

        // Everything else (`aget`, unknown PHP fns) walks arg expressions
        // for nested operators without constraining the local: unknown
        // functions could accept anything.
        $this->walkArgsExpecting($node, null);
    }

    private function walkGlobalCall(CallNode $node, GlobalVarNode $fn, ?string $expected): void
    {
        if ($this->isSelfReference($fn)) {
            $this->walkArgsExpecting($node, null);
            return;
        }

        if ($expected === 'int'
            && $this->resolver->isIntStableCoreFn($fn)
            && !$this->resolver->hasNonIntLiteralArg($node)
        ) {
            $this->walkArgsExpecting($node, 'int');
            return;
        }

        if ($this->resolver->isCoreNumericObserver($fn) && !$this->resolver->hasNumericObjectLiteralArg($node)) {
            $this->walkCoreNumericCall($node, $expected);
            return;
        }

        if ($this->resolver->isCoreOrderingObserver($fn) && !$this->resolver->hasNumericObjectLiteralArg($node)) {
            $this->walkCoreNumericCall($node, $expected);
            return;
        }

        if ($this->resolver->isCoreEqualityObserver($fn)) {
            $this->walkIdentityCall($node);
            return;
        }

        $this->walkArgsBySignature($node, $this->resolver->calleeParamExpectations($fn));
    }

    /**
     * `(+ a 1.5)`, `(>= a 0.5)`, etc. — `phel.core` arithmetic and
     * ordering wrappers stay polymorphic between `int`, `float`,
     * `BigInt`, and `Ratio` at runtime, so the inferrer must not narrow
     * to `int` from an int literal sibling: `(pos? 0.1)` already calls
     * `(> x 0)` against a float in core. A `float` tag, on the other
     * hand, is safe because PHP `float $x` widens to accept int callers
     * via implicit coercion. We only commit when a float literal makes
     * the runtime float path inevitable. Expectations flowing from
     * above are still honoured.
     */
    private function walkCoreNumericCall(CallNode $node, ?string $expected = null): void
    {
        $type = $this->resolver->literalNumericType($node->getArguments());

        if ($type === 'float') {
            $this->walkArgsExpecting($node, 'float');
            return;
        }

        if ($expected === 'int' || $expected === 'float') {
            $this->walkArgsExpecting($node, $expected);
            return;
        }

        $this->walkArgsExpecting($node, null);
    }

    private function isSelfReference(GlobalVarNode $fn): bool
    {
        return $this->selfNamespace !== null
            && $this->selfName !== null
            && $fn->getNamespace() === $this->selfNamespace
            && $fn->getName()->getName() === $this->selfName;
    }

    /**
     * `(php/=== x nil)` and friends are how Phel code type-discriminates
     * before concatenating or arithmetic'ing the value. When a param is
     * compared to `nil`, `true`, or `false`, the body branches that
     * "look like a primitive use" only fire after the user has already
     * filtered the off-type values out. Marking the param guarded keeps
     * the runtime contract permissive so callers can still pass the
     * full union the body actually accepts.
     */
    private function walkIdentityCall(CallNode $node): void
    {
        if ($this->resolver->hasNullableLiteralArg($node)) {
            $this->walkArgsAsGuarded($node);
            return;
        }

        $this->walkArgsExpecting($node, null);
    }

    /**
     * `<`, `>`, `<=`, `>=`, `<=>` against a numeric literal hint that the
     * param is meant to be numeric. We treat the comparison as a soft
     * observation so a body that *also* concatenates the same param ends
     * up with disagreeing observations and drops out, leaving the runtime
     * contract permissive in the face of a coerce-then-concat pattern
     * (e.g. `(php/> x 0)` followed by `(php/. "" x)`).
     */
    private function walkOrderingCall(CallNode $node): void
    {
        $type = $this->resolver->literalNumericType($node->getArguments());
        $this->walkArgsExpecting($node, $type);
    }

    /**
     * `(php/+ x ...)` and friends. We only commit to a numeric type when
     * a literal in the same call disambiguates int vs float, or when an
     * `int` / `float` expectation flows in from above (Step 3). Without
     * either signal, mixing call expressions for both operands (e.g.
     * `(php/+ (php/- zx2 zy2) cx)` in a float Mandelbrot kernel) would
     * over-narrow the param to int. Walking arg expressions still
     * captures any nested operator without polluting the local.
     */
    private function walkNumericCall(CallNode $node, ?string $expected = null): void
    {
        $type = $this->resolver->literalNumericType($node->getArguments());
        if ($type === null && ($expected === 'int' || $expected === 'float')) {
            $type = $expected;
        }

        $this->walkArgsExpecting($node, $type);
    }

    /**
     * Walks every arg with the same expectation. `null` flows through
     * unchanged so callers that just want to traverse nested operators
     * without constraining a slot share the same path as callers that
     * push a concrete primitive tag down.
     */
    private function walkArgsExpecting(CallNode $node, ?string $expected): void
    {
        foreach ($node->getArguments() as $arg) {
            $this->walk($arg, $expected);
        }
    }

    /**
     * @param list<?string> $expectations
     */
    private function walkArgsBySignature(CallNode $node, array $expectations): void
    {
        $count = count($expectations);
        foreach ($node->getArguments() as $i => $arg) {
            $this->walk($arg, $i < $count ? $expectations[$i] : null);
        }
    }

    private function walkArgsAsGuarded(CallNode $node): void
    {
        foreach ($node->getArguments() as $arg) {
            $this->markGuarded($arg);
            $this->walk($arg);
        }
    }

    private function constrainArgAsScalar(AbstractNode $arg, string $type): void
    {
        $name = $this->paramNameOf($arg);
        if ($name !== null) {
            $this->observations[$name][] = $type;
        }
    }

    private function markGuarded(AbstractNode $arg): void
    {
        $name = $this->paramNameOf($arg);
        if ($name !== null) {
            $this->guarded[$name] = true;
        }
    }

    private function paramNameOf(AbstractNode $arg): ?string
    {
        if (!$arg instanceof LocalVarNode) {
            return null;
        }

        $name = $arg->getName()->getName();
        return isset($this->params[$name]) ? $name : null;
    }
}
