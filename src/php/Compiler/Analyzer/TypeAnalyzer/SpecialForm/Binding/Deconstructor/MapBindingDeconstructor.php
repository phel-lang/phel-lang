<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;

/**
 * @implements BindingDeconstructorInterface<PersistentMapInterface>
 */
final class MapBindingDeconstructor implements BindingDeconstructorInterface
{
    private Deconstructor $deconstructor;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $mapSymbol;

    public function __construct(Deconstructor $deconstructor)
    {
        $this->deconstructor = $deconstructor;
    }

    /**
     * @param PersistentMapInterface $binding The binding form
     * @param TypeInterface|string|float|int|bool|null $value The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $this->mapSymbol = Symbol::gen()->copyLocationFrom($binding);
        $bindings[] = [$this->mapSymbol, $value];

        foreach ($binding as $key => $bindTo) {
            $this->bindingIteration($bindings, $binding, $key, $bindTo);
        }
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $key
     * @param TypeInterface|string|float|int|bool|null $bindTo
     */
    private function bindingIteration(array &$bindings, PersistentMapInterface $binding, $key, $bindTo): void
    {
        $accessSymbol = Symbol::gen()->copyLocationFrom($binding);
        $accessValue = $this->createAccessValue($binding, $key);
        $bindings[] = [$accessSymbol, $accessValue];

        $this->deconstructor->deconstructBindings($bindings, $bindTo, $accessSymbol);
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $key
     */
    private function createAccessValue(PersistentMapInterface $binding, $key): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))->copyLocationFrom($binding),
            $this->mapSymbol,
            $key,
        ])->copyLocationFrom($binding);
    }
}
