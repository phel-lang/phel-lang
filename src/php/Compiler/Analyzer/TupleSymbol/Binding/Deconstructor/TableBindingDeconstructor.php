<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Lang\AbstractType;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

/**
 * @implements BindingDeconstructorInterface<Table>
 */
final class TableBindingDeconstructor implements BindingDeconstructorInterface
{
    private TupleDeconstructor $tupleDeconstructor;

    public function __construct(TupleDeconstructor $deconstructor)
    {
        $this->tupleDeconstructor = $deconstructor;
    }

    /**
     * @param Table $binding The binding form
     * @param AbstractType|string|float|int|bool|null $value The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $tableSymbol = Symbol::gen()->copyLocationFrom($binding);
        $bindings[] = [$tableSymbol, $value];

        foreach ($binding as $key => $bindTo) {
            $accessSym = Symbol::gen()->copyLocationFrom($binding);
            $accessValue = $this->createAccessValue($binding, $tableSymbol, $key);
            $bindings[] = [$accessSym, $accessValue];

            $this->tupleDeconstructor->deconstructBindings($bindings, $bindTo, $accessSym);
        }
    }

    /**
     * @param AbstractType|string|float|int|bool|null $key
     */
    private function createAccessValue(Table $binding, Symbol $tableSymbol, $key): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))
                ->copyLocationFrom($binding),
            $tableSymbol,
            $key
        )->copyLocationFrom($binding);
    }
}
