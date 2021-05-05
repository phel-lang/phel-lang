<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;

/**
 * @implements BindingDeconstructorInterface<Table>
 */
final class TableBindingDeconstructor implements BindingDeconstructorInterface
{
    private Deconstructor $deconstructor;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $tableSymbol;

    public function __construct(Deconstructor $deconstructor)
    {
        $this->deconstructor = $deconstructor;
    }

    /**
     * @param Table $binding The binding form
     * @param TypeInterface|string|float|int|bool|null $value The value form
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
     * @param TypeInterface|string|float|int|bool|null $key
     * @param TypeInterface|string|float|int|bool|null $bindTo
     */
    private function bindingIteration(array &$bindings, Table $binding, $key, $bindTo): void
    {
        $accessSymbol = Symbol::gen()->copyLocationFrom($binding);
        $accessValue = $this->createAccessValue($binding, $key);
        $bindings[] = [$accessSymbol, $accessValue];

        $this->deconstructor->deconstructBindings($bindings, $bindTo, $accessSymbol);
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $key
     */
    private function createAccessValue(Table $binding, $key): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))->copyLocationFrom($binding),
            $this->tableSymbol,
            $key,
        ])->copyLocationFrom($binding);
    }
}
