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
    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $arrSymbol;

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
        $this->arrSymbol = Symbol::gen()->copyLocationFrom($binding);
        $bindings[] = [$this->arrSymbol, $value];

        for ($i = 0, $iMax = count($binding); $i < $iMax; $i += 2) {
            $this->bindingIteration($bindings, $binding, $i);
        }
    }

    private function bindingIteration(array &$bindings, PhelArray $binding, int $i): void
    {
        $index = $binding[$i];
        $bindTo = $binding[$i + 1];
        $accessSymbol = Symbol::gen()->copyLocationFrom($binding);
        $accessValue = $this->createBindingValue($binding, $index);
        $bindings[] = [$accessSymbol, $accessValue];

        $this->tupleDeconstructor->deconstructBindings($bindings, $bindTo, $accessSymbol);
    }

    /**
     * @param mixed $index
     */
    private function createBindingValue(PhelArray $binding, $index): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))->copyLocationFrom($binding),
            $this->arrSymbol,
            $index
        )->copyLocationFrom($binding);
    }
}
