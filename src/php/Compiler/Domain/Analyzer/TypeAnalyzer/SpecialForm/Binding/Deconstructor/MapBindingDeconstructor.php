<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

/**
 * @implements BindingDeconstructorInterface<PersistentMapInterface>
 */
final class MapBindingDeconstructor implements BindingDeconstructorInterface
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $mapSymbol;

    public function __construct(
        private readonly Deconstructor $deconstructor,
    ) {
    }

    /**
     * @param PersistentMapInterface                   $binding The binding form
     * @param bool|float|int|string|TypeInterface|null $value   The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $keys = null;
        $asSymbol = null;
        $normalBindings = [];

        foreach ($binding as $key => $bindTo) {
            if ($key instanceof Keyword && $key->getName() === 'keys') {
                $keys = $bindTo;
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'as') {
                $asSymbol = $bindTo;
                continue;
            }

            $normalBindings[] = [$key, $bindTo];
        }

        $this->mapSymbol = $asSymbol instanceof Symbol
            ? $asSymbol
            : Symbol::gen()->copyLocationFrom($binding);

        $bindings[] = [$this->mapSymbol, $value];

        if ($keys instanceof PersistentVectorInterface) {
            foreach ($keys as $sym) {
                if ($sym instanceof Symbol) {
                    $keyword = Keyword::create($sym->getName());
                    $this->bindingIteration($bindings, $binding, $keyword, $sym);
                }
            }
        }

        foreach ($normalBindings as [$key, $bindTo]) {
            $this->bindingIteration($bindings, $binding, $key, $bindTo);
        }
    }

    private function bindingIteration(
        array &$bindings,
        PersistentMapInterface $binding,
        TypeInterface|string|float|int|bool|null $key,
        TypeInterface|string|float|int|bool|null $bindTo,
    ): void {
        $accessSymbol = Symbol::gen()->copyLocationFrom($binding);
        $accessValue = $this->createAccessValue($binding, $key);
        $bindings[] = [$accessSymbol, $accessValue];

        $this->deconstructor->deconstructBindings($bindings, $bindTo, $accessSymbol);
    }

    private function createAccessValue(
        PersistentMapInterface $binding,
        float|bool|int|string|TypeInterface|null $key,
    ): PersistentListInterface {
        return Phel::list([
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))->copyLocationFrom($binding),
            $this->mapSymbol,
            $key,
        ])->copyLocationFrom($binding);
    }
}
