<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

/**
 * @implements BindingDeconstructorInterface<PhelArray>
 */
final class PhelArrayBindingDeconstructor implements BindingDeconstructorInterface
{
    private TupleDeconstructor $tupleDeconstructor;

    public function __construct(TupleDeconstructor $deconstructor)
    {
        $this->tupleDeconstructor = $deconstructor;
    }

    /**
     * @param PhelArray $binding The binding form
     * @param AbstractType|string|float|int|bool|null $value The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $arrSymbol = Symbol::gen()->copyLocationFrom($binding);
        $bindings[] = [$arrSymbol, $value];

        for ($i = 0, $iMax = count($binding); $i < $iMax; $i += 2) {
            $index = $binding[$i];
            $bindTo = $binding[$i + 1];

            $accessSym = Symbol::gen()->copyLocationFrom($binding);
            $accessValue = Tuple::create(
                (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))
                    ->copyLocationFrom($binding),
                $arrSymbol,
                $index
            )->copyLocationFrom($binding);
            $bindings[] = [$accessSym, $accessValue];

            $this->tupleDeconstructor->deconstructBindings($bindings, $bindTo, $accessSym);
        }
    }
}
