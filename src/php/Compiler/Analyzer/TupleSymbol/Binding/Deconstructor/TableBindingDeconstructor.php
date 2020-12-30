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
    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $tableSymbol;

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
        $this->tableSymbol = Symbol::gen()->copyLocationFrom($binding);
        $bindings[] = [$this->tableSymbol, $value];

        foreach ($binding as $key => $bindTo) {
            $this->bindingIteration($bindings, $binding, $key, $bindTo);
        }
    }

    /**
     * @param AbstractType|string|float|int|bool|null $key
     * @param AbstractType|string|float|int|bool|null $bindTo
     */
    private function bindingIteration(array &$bindings, Table $binding, $key, $bindTo): void
    {
        $accessSymbol = Symbol::gen()->copyLocationFrom($binding);
        $accessValue = $this->createAccessValue($binding, $key);
        $bindings[] = [$accessSymbol, $accessValue];

        $this->tupleDeconstructor->deconstructBindings($bindings, $bindTo, $accessSymbol);
    }

    /**
     * @param AbstractType|string|float|int|bool|null $key
     */
    private function createAccessValue(Table $binding, $key): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))->copyLocationFrom($binding),
            $this->tableSymbol,
            $key
        )->copyLocationFrom($binding);
    }
}
