<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;

/**
 * Call-site eligibility for the `phel.core` collection constructors, which
 * {@see NodeEmitter\CallEmitter} lowers to the `Phel` static factory the
 * runtime function would have called anyway.
 *
 * Each of these is defined in `src/phel/core.phel` as a bare variadic:
 *
 *     (def list ... (fn [& xs] (Phel/list (apply php/array xs))))
 *
 * so a literal `(list 1 2 3)` pays a variadic parameter, an `apply`, and an
 * `__invoke` through the fn object, only to reach `\Phel::list([1, 2, 3])`.
 * At a call site the argument count is known, so the emitter can write that
 * destination directly.
 *
 * Unlike the other families here this one needs no analyser tag: the lowering
 * is justified by the callee's identity alone, not by anything known about the
 * arguments. `PhelCoreCall::nameOf()` supplies that identity, and a local
 * shadow is a `LocalVarNode` rather than a `GlobalVarNode`, so `(let [list …]
 * (list 1))` keeps calling the local.
 *
 * Arity is deliberately unconstrained, including the odd argument counts that
 * are an error for the map constructors. `\Phel::map([1])` throws the same
 * "An even number of elements must be provided to build a map" the runtime
 * function reaches, so routing every count here leaves that error untouched.
 *
 * `apply` and higher-order uses (`(map list …)`) are unaffected: neither puts
 * the constructor in call-head position, so both keep the runtime fn.
 *
 * @internal
 */
final readonly class ConstructorSpecialization
{
    /** @var array<string, string> phel.core constructor => Phel static factory */
    private const array FACTORIES = [
        'list' => 'list',
        'vector' => 'vector',
        'queue' => 'queue',
        'hash-map' => 'map',
        'array-map' => 'map',
    ];

    private function __construct() {}

    /**
     * The `Phel` static factory method this call collapses to, or null when
     * the call is not to a `phel.core` collection constructor.
     */
    public static function factoryMethod(CallNode $node): ?string
    {
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        return self::FACTORIES[$name] ?? null;
    }

    public static function isConstructorCall(CallNode $node): bool
    {
        return self::factoryMethod($node) !== null;
    }
}
