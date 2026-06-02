<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Shared\CompilerConstants;

use function count;
use function in_array;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function strlen;

/**
 * Stateless shape analysis for {@see ParamTypeInferrer}. Classifies PHP-op
 * and `phel.core` calls and reads literal/callee type hints, answering
 * "what scalar expectation (if any) does this call shape imply, and does it
 * guard its args?". The walker and its mutable observation state live in the
 * inferrer; everything here is a pure function of the node graph.
 */
final readonly class CallTypeExpectationResolver
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
     * permissive so `BigInt` / `Ratio` polymorphism keeps
     * working at the call site.
     *
     * @var list<string>
     */
    private const array INT_STABLE_CORE_FNS = ['+', '-', '*', 'inc', 'dec'];

    /**
     * `phel.core` arithmetic wrappers whose dispatch reduces to a PHP
     * numeric op only when a literal sibling proves the operand is
     * `float`. An `int` literal stays ambiguous (it coerces to float
     * inside the runtime defn when the LocalVar is float at the call
     * site), so the wrapper observer only commits when the literal is
     * unambiguously float. `php/+`, `php/-` keep their own int
     * inference path because the user reached for the PHP-native op
     * directly.
     *
     * @var list<string>
     */
    private const array NUMERIC_CORE_OBSERVERS = ['+', '-', '*', 'inc', 'dec'];

    /**
     * `phel.core` ordering wrappers. Each one reduces to the matching
     * `php/<`, `php/<=`, ... when no `BigInt` / `Ratio` shows up at
     * runtime, so the ordering walker can lift a numeric tag the same
     * way it does for the PHP-native op.
     *
     * @var list<string>
     */
    private const array ORDERING_CORE_OBSERVERS = ['<', '<=', '>', '>=', '<=>'];

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

    public function isGuardPhpFn(string $op): bool
    {
        return in_array($op, self::GUARD_PHP_FNS, true);
    }

    public function isStringConcatOp(string $op): bool
    {
        return $op === self::STRING_CONCAT_OP;
    }

    public function isNumericOp(string $op): bool
    {
        return in_array($op, self::NUMERIC_OPS, true);
    }

    public function isIdentityOp(string $op): bool
    {
        return in_array($op, self::IDENTITY_OPS, true);
    }

    public function isOrderingOp(string $op): bool
    {
        return in_array($op, self::ORDERING_OPS, true);
    }

    /**
     * The per-slot expected tags for a curated PHP stdlib fn, or `null`
     * when the fn is not on the allowlist.
     *
     * @return list<?string>|null
     */
    public function phpFnSignature(string $op): ?array
    {
        return self::PHP_FN_SIGNATURES[$op] ?? null;
    }

    public function isGuardGlobal(GlobalVarNode $fn): bool
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

    public function isIntStableCoreFn(GlobalVarNode $fn): bool
    {
        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return false;
        }

        return in_array($fn->getName()->getName(), self::INT_STABLE_CORE_FNS, true);
    }

    public function isCoreNumericObserver(GlobalVarNode $fn): bool
    {
        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return false;
        }

        return in_array($fn->getName()->getName(), self::NUMERIC_CORE_OBSERVERS, true);
    }

    public function isCoreOrderingObserver(GlobalVarNode $fn): bool
    {
        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return false;
        }

        return in_array($fn->getName()->getName(), self::ORDERING_CORE_OBSERVERS, true);
    }

    public function isCoreEqualityObserver(GlobalVarNode $fn): bool
    {
        return $fn->getNamespace() === CompilerConstants::PHEL_CORE_NAMESPACE
            && $fn->getName()->getName() === '=';
    }

    /**
     * `BigInt`, `Ratio`, `BigDecimal` literals route through
     * `NumericOperations` at runtime; the int / float observer path
     * must skip the call so the inferrer never narrows a param that
     * a numeric-object literal disambiguates as polymorphic. Unlike
     * {@see self::hasNonIntLiteralArg()}, a float literal is treated
     * as a valid numeric signal here, not as noise.
     */
    public function hasNumericObjectLiteralArg(CallNode $node): bool
    {
        return array_any(
            $node->getArguments(),
            static fn(AbstractNode $arg): bool => $arg instanceof LiteralNode
                && is_object($arg->getValue()),
        );
    }

    /**
     * Detects literal args that the int-stable core fns explicitly
     * accept via runtime polymorphism (`BigInt`, `Ratio`,
     * `BigDecimal`, floats). Their presence signals that the caller
     * is deliberately routing through `NumericOperations`, so the
     * sibling param's runtime contract must stay permissive even when
     * an `int` expectation flows in from above.
     */
    public function hasNonIntLiteralArg(CallNode $node): bool
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

    /**
     * True when any literal arg is `nil`, `true`, or `false` — the shape
     * of an identity comparison (`(php/=== x nil)`) the user writes to
     * type-discriminate before treating the param as a primitive.
     */
    public function hasNullableLiteralArg(CallNode $node): bool
    {
        return array_any(
            $node->getArguments(),
            static fn(AbstractNode $a): bool => $a instanceof LiteralNode
                && self::isNullableLiteral($a->getValue()),
        );
    }

    /**
     * Reads a numeric type hint from the literal args of a call: `float`
     * if any literal is float, `int` if any literal is int and none are
     * float, otherwise `null`. The numeric and ordering walkers use this
     * so they only constrain a param when the call actually disambiguates
     * the runtime type.
     *
     * @param list<AbstractNode> $args
     */
    public function literalNumericType(array $args): ?string
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
     * Builds a per-slot list of expected pure-primitive tags for the
     * callee. Compile-time `:param-tags` win; if that channel is empty
     * (e.g. core sub-namespaces loaded from a precompiled cache that
     * the analyzer never sees), `phel.core` fns fall back to the
     * `CORE_FN_SIGNATURES` allowlist so cross-namespace inference can
     * still propagate.
     *
     * @return list<?string>
     */
    public function calleeParamExpectations(GlobalVarNode $fn): array
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

    private static function isNullableLiteral(mixed $value): bool
    {
        return in_array($value, [null, true, false], true);
    }
}
