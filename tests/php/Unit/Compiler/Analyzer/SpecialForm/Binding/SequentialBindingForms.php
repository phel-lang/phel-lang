<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding;

use Phel;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReturnTypeInferrer;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Destructure;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

/**
 * The forms a sequential pattern expands into (#3356), spelled out once for
 * the deconstructor tests: a vector reads by index, anything else walks.
 */
final class SequentialBindingForms
{
    /**
     * A binding symbol the expansion adds, marked as compiler plumbing.
     */
    public static function synthetic(string $name): Symbol
    {
        return Symbol::create($name)
            ->withMeta(Phel::map(Keyword::create(ReturnTypeInferrer::SYNTHETIC_BINDING), true));
    }

    /**
     * `(php/instanceof value \Phel\Lang\Collections\Vector\PersistentVectorInterface)`.
     *
     * @return PersistentListInterface<mixed>
     */
    public static function isVector(string $value): PersistentListInterface
    {
        return Phel::list([
            Symbol::create('php/instanceof'),
            Symbol::create($value),
            Symbol::create('\\' . PersistentVectorInterface::class),
        ]);
    }

    /**
     * `(if (php/=== test true) (php/aget value index) (first walk))`.
     *
     * @return PersistentListInterface<mixed>
     */
    public static function positional(string $test, string $value, string $walk, int $index): PersistentListInterface
    {
        return self::guard(
            $test,
            Phel::list([
                Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                Symbol::create($value),
                $index,
            ]),
            Phel::list([Symbol::create('first'), self::walk($value, $walk)]),
        );
    }

    /**
     * `(if (php/=== test true) nil (next walk))`.
     *
     * @return PersistentListInterface<mixed>
     */
    public static function step(string $test, string $value, string $walk): PersistentListInterface
    {
        return self::guard(
            $test,
            null,
            Phel::list([Symbol::create('next'), self::walk($value, $walk)]),
        );
    }

    /**
     * `(if (php/=== test true) (php/-> value (cdr)) walk)` after one position,
     * `(if (php/=== test true) (php/:: \Phel\Lang\Destructure (nthNext value index)) walk)` after more.
     *
     * @return PersistentListInterface<mixed>
     */
    public static function rest(string $test, string $value, string $walk, int $index): PersistentListInterface
    {
        $vectorTail = $index === 1
            ? Phel::list([
                Symbol::create(Symbol::NAME_PHP_OBJECT_CALL),
                Symbol::create($value),
                Phel::list([Symbol::create('cdr')]),
            ])
            : Phel::list([
                Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL),
                Symbol::create('\\' . Destructure::class),
                Phel::list([Symbol::create('nthNext'), Symbol::create($value), $index]),
            ]);

        return self::guard($test, $vectorTail, self::walk($value, $walk));
    }

    /**
     * The walk starts at the value, the user's binding, and continues through
     * the `next` steps the expansion adds.
     */
    private static function walk(string $value, string $walk): Symbol
    {
        return $walk === $value ? Symbol::create($walk) : self::synthetic($walk);
    }

    /**
     * @return PersistentListInterface<mixed>
     */
    private static function guard(string $test, mixed $then, mixed $else): PersistentListInterface
    {
        return Phel::list([
            Symbol::create(Symbol::NAME_IF),
            Phel::list([Symbol::create('php/==='), self::synthetic($test), true]),
            $then,
            $else,
        ]);
    }
}
