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
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;

use function array_unique;
use function count;
use function in_array;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function strlen;

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
 * Three cross-fn propagation channels extend the basic primitive-op
 * inference:
 *   - callee `:param-tags` (Step 1): when the call target's params are
 *     already tagged with a pure primitive, the matching call-site args
 *     inherit that tag.
 *   - PHP host signature table (Step 2): a curated allowlist of stdlib
 *     functions whose arg types are stable across versions.
 *   - expected-type back-pressure (Step 3): a tag flowing in from a
 *     callee or signature table can push down into nested numeric ops,
 *     so `(php/random_int 0 (php/- hi lo))` flows int into `hi`/`lo`.
 *   - `:int-stable` core fns (Step 4): an allowlist of `phel.core`
 *     arithmetic fns that, given an `int` expectation from above, behave
 *     like a numeric op for the purposes of propagation.
 */
final class ParamTypeInferrer
{
    /** @var list<string> */
    private const array NUMERIC_OPS = [
        '+', '-', '*', '/', '%', '**', '<<', '>>', '|', '&', '^',
    ];

    private const string STRING_CONCAT_OP = '.';

    /** @var list<string> */
    private const array IDENTITY_OPS = ['===', '!==', '==', '!='];

    /** @var list<string> */
    private const array ORDERING_OPS = ['<', '>', '<=', '>=', '<=>'];

    /**
     * Tags that translate directly to a single PHP scalar and therefore
     * survive being grafted onto a param without losing valid runtime
     * inputs. Nullable (`?int`), union (`int|null`), and FQN tags are
     * deliberately excluded: tightening them to bare `int` rejects
     * callers that the callee already accepts.
     *
     * @var list<string>
     */
    private const array PURE_PRIMITIVE_TAGS = ['int', 'float', 'string', 'bool', 'array'];

    /**
     * Curated allowlist of PHP stdlib fns whose arg types are stable
     * across supported versions. Each entry's value lists the scalar tag
     * inferred per positional arg (use `null` to skip a slot). Unions
     * and nullable types stay out so the inferrer never tightens a slot
     * the runtime accepts more loosely.
     *
     * @var array<string, list<?string>>
     */
    private const array PHP_FN_SIGNATURES = [
        'random_int' => ['int', 'int'],
        'intdiv' => ['int', 'int'],
        'strlen' => ['string'],
        'mb_strlen' => ['string'],
        'str_repeat' => ['string', 'int'],
        'count' => ['array'],
    ];

    /**
     * `phel.core` arithmetic fns whose return is `int` when every arg
     * is `int`. The inferrer treats them as conditional numeric ops:
     * propagation only fires when an `int` expectation flows in from
     * above. Without that expectation we leave the runtime contract
     * permissive so `BigInteger` / `Rational` polymorphism keeps
     * working at the call site.
     *
     * @var list<string>
     */
    private const array INT_STABLE_CORE_FNS = ['+', '-', '*', 'inc', 'dec'];

    /**
     * Static fallback for `phel.core` callees whose compile-time
     * `:param-tags` channel is not available (the runtime registry is
     * populated from a precompiled cache, so the analyzer never sees
     * the sub-namespaces' defns and the `:param-tags` vector stays
     * empty). The mapping mirrors the strict slot-by-slot
     * primitive-tag contract that the runtime fn already enforces.
     *
     * @var array<string, list<?string>>
     */
    private const array CORE_FN_SIGNATURES = [
        'rand-int' => ['int'],
    ];

    /**
     * Globals that signal "the function defensively rejects bad inputs at
     * runtime". A param threaded through any of these escapes inference
     * even when later used by a primitive op, so deliberate negative tests
     * (e.g. `(bit-and nil 1)` against an `assert-non-nil` guard) keep
     * compiling and reach the runtime guard.
     *
     * @var list<string>
     */
    private const array GUARD_GLOBALS = [
        'assert-non-nil',
        'assert',
    ];

    /**
     * PHP type predicates used as type-discriminating guards. When a
     * param is fed to one of these, the user is admitting the value
     * could be of multiple types, so the runtime contract must stay
     * permissive even if a sibling branch concatenates or arithmetic's
     * the same param.
     *
     * @var list<string>
     */
    private const array GUARD_PHP_FNS = [
        'is_int', 'is_integer', 'is_long',
        'is_float', 'is_double',
        'is_string',
        'is_bool',
        'is_null',
        'is_array',
        'is_object',
        'is_callable',
        'is_numeric',
        'is_iterable',
        'is_countable',
        'is_scalar',
    ];

    /** @var array<string, list<string>> */
    private array $observations = [];

    /** @var array<string, true> */
    private array $params = [];

    /** @var array<string, true> */
    private array $guarded = [];

    private ?string $selfNamespace = null;

    private ?string $selfName = null;

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

        if ($fn instanceof GlobalVarNode && $this->isGuardGlobal($fn)) {
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

        if (in_array($op, self::GUARD_PHP_FNS, true)) {
            $this->walkArgsAsGuarded($node);
            return;
        }

        if ($op === self::STRING_CONCAT_OP) {
            $this->walkArgsExpecting($node, 'string');
            return;
        }

        if (in_array($op, self::NUMERIC_OPS, true)) {
            $this->walkNumericCall($node, $expected);
            return;
        }

        if (in_array($op, self::IDENTITY_OPS, true)) {
            $this->walkIdentityCall($node);
            return;
        }

        if (in_array($op, self::ORDERING_OPS, true)) {
            $this->walkOrderingCall($node);
            return;
        }

        if (isset(self::PHP_FN_SIGNATURES[$op])) {
            $this->walkArgsBySignature($node, self::PHP_FN_SIGNATURES[$op]);
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
            && $this->isIntStableCoreFn($fn)
            && !$this->hasNonIntLiteralArg($node)
        ) {
            $this->walkArgsExpecting($node, 'int');
            return;
        }

        $this->walkArgsBySignature($node, $this->calleeParamExpectations($fn));
    }

    private function isSelfReference(GlobalVarNode $fn): bool
    {
        return $this->selfNamespace !== null
            && $this->selfName !== null
            && $fn->getNamespace() === $this->selfNamespace
            && $fn->getName()->getName() === $this->selfName;
    }

    /**
     * Builds a per-slot list of expected pure-primitive tags for the
     * callee. Compile-time `:param-tags` win; if that channel is empty
     * (e.g. core sub-namespaces loaded from a precompiled cache that
     * the analyzer never sees), `phel.core` fns fall back to the
     * `CORE_FN_SIGNATURES` allowlist so cross-namespace inference can
     * still propagate.
     *
     * @return list<?string>
     */
    private function calleeParamExpectations(GlobalVarNode $fn): array
    {
        $paramTags = $fn->getMeta()->find(Keyword::create('param-tags'));
        if ($paramTags instanceof PersistentVectorInterface && count($paramTags) > 0) {
            $expectations = [];
            $hasPrimitive = false;
            foreach ($paramTags as $tag) {
                if (is_string($tag) && in_array($tag, self::PURE_PRIMITIVE_TAGS, true)) {
                    $expectations[] = $tag;
                    $hasPrimitive = true;
                } else {
                    $expectations[] = null;
                }
            }

            if ($hasPrimitive) {
                return $expectations;
            }
        }

        if ($fn->getNamespace() === CompilerConstants::PHEL_CORE_NAMESPACE) {
            return self::CORE_FN_SIGNATURES[$fn->getName()->getName()] ?? [];
        }

        return [];
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
        $hasNullableLiteral = array_any(
            $node->getArguments(),
            static fn(AbstractNode $a): bool => $a instanceof LiteralNode
                && self::isNullableLiteral($a->getValue()),
        );

        if ($hasNullableLiteral) {
            $this->walkArgsAsGuarded($node);
            return;
        }

        $this->walkArgsExpecting($node, null);
    }

    private static function isNullableLiteral(mixed $value): bool
    {
        return in_array($value, [null, true, false], true);
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
        $type = $this->literalNumericType($node->getArguments());
        $this->walkArgsExpecting($node, $type);
    }

    /**
     * Reads a numeric type hint from the literal args of a call: `float`
     * if any literal is float, `int` if any literal is int and none are
     * float, otherwise `null`. Both `walkNumericCall` and
     * `walkOrderingCall` need this so they only constrain a param when
     * the call actually disambiguates the runtime type.
     *
     * @param list<AbstractNode> $args
     */
    private function literalNumericType(array $args): ?string
    {
        $hasFloat = false;
        $hasInt = false;
        foreach ($args as $arg) {
            if (!$arg instanceof LiteralNode) {
                continue;
            }

            $value = $arg->getValue();
            if (is_float($value)) {
                $hasFloat = true;
            } elseif (is_int($value)) {
                $hasInt = true;
            }
        }

        if ($hasFloat) {
            return 'float';
        }

        return $hasInt ? 'int' : null;
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
        $type = $this->literalNumericType($node->getArguments());
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

    private function isGuardGlobal(GlobalVarNode $fn): bool
    {
        $name = $fn->getName()->getName();
        if (in_array($name, self::GUARD_GLOBALS, true)) {
            return true;
        }

        // Phel convention: predicate names end in `?`. Calling one on a
        // param signals "this value can be of multiple types, I'm
        // type-discriminating" (same intent as the explicit `assert*`
        // guards), so mark the arg guarded.
        return $name !== '' && $name[strlen($name) - 1] === '?';
    }

    /**
     * Detects literal args that the int-stable core fns explicitly
     * accept via runtime polymorphism (`BigInteger`, `Rational`,
     * `BigDecimal`, floats). Their presence signals that the caller
     * is deliberately routing through `NumericOperations`, so the
     * sibling param's runtime contract must stay permissive even when
     * an `int` expectation flows in from above.
     */
    private function hasNonIntLiteralArg(CallNode $node): bool
    {
        return array_any(
            $node->getArguments(),
            static function (AbstractNode $arg): bool {
                if (!$arg instanceof LiteralNode) {
                    return false;
                }

                $value = $arg->getValue();
                if (is_int($value)) {
                    return false;
                }

                return is_float($value) || is_object($value);
            },
        );
    }

    private function isIntStableCoreFn(GlobalVarNode $fn): bool
    {
        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return false;
        }

        return in_array($fn->getName()->getName(), self::INT_STABLE_CORE_FNS, true);
    }
}
