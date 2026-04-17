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

use function array_key_exists;

/**
 * @implements BindingDeconstructorInterface<PersistentMapInterface>
 */
final class MapBindingDeconstructor implements BindingDeconstructorInterface
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $mapSymbol;

    /** @var array<string, bool|float|int|string|TypeInterface|null> */
    private array $orDefaults = [];

    public function __construct(
        private readonly Deconstructor $deconstructor,
    ) {}

    /**
     * @param PersistentMapInterface                   $binding The binding form
     * @param bool|float|int|string|TypeInterface|null $value   The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $keys = null;
        $strs = null;
        $syms = null;
        $asSymbol = null;
        $orMap = null;
        $normalBindings = [];

        foreach ($binding as $key => $bindTo) {
            if ($key instanceof Keyword && $key->getName() === 'keys') {
                $keys = $bindTo;
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'strs') {
                $strs = $bindTo;
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'syms') {
                $syms = $bindTo;
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'as') {
                $asSymbol = $bindTo;
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'or') {
                $orMap = $bindTo;
                continue;
            }

            $normalBindings[] = [$key, $bindTo];
        }

        $this->orDefaults = [];
        if ($orMap instanceof PersistentMapInterface) {
            foreach ($orMap as $sym => $default) {
                if ($sym instanceof Symbol) {
                    $this->orDefaults[$sym->getName()] = $default;
                }
            }
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

        if ($strs instanceof PersistentVectorInterface) {
            foreach ($strs as $sym) {
                if ($sym instanceof Symbol) {
                    $this->bindingIteration($bindings, $binding, $sym->getName(), $sym);
                }
            }
        }

        if ($syms instanceof PersistentVectorInterface) {
            foreach ($syms as $sym) {
                if ($sym instanceof Symbol) {
                    $quotedSym = Phel::list([
                        Symbol::create(Symbol::NAME_QUOTE)->copyLocationFrom($binding),
                        Symbol::create($sym->getName())->copyLocationFrom($binding),
                    ])->copyLocationFrom($binding);
                    $this->bindingIteration($bindings, $binding, $quotedSym, $sym);
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
        $default = $this->findDefault($bindTo);
        $accessValue = $default !== null
            ? $this->createAccessValueWithDefault($binding, $key, $default[0])
            : $this->createAccessValue($binding, $key);
        $bindings[] = [$accessSymbol, $accessValue];

        $this->deconstructor->deconstructBindings($bindings, $bindTo, $accessSymbol);
    }

    /**
     * Returns [default] if the binding symbol has an :or default, null otherwise.
     * Wrapped in an array to distinguish "no default" from "default is null".
     *
     * @return array{bool|float|int|string|TypeInterface|null}|null
     */
    private function findDefault(
        TypeInterface|string|float|int|bool|null $bindTo,
    ): ?array {
        if (!$bindTo instanceof Symbol) {
            return null;
        }

        $name = $bindTo->getName();

        if (!array_key_exists($name, $this->orDefaults)) {
            return null;
        }

        return [$this->orDefaults[$name]];
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

    /**
     * Generates: (if (contains? mapSym key) (php/aget mapSym key) default)
     */
    private function createAccessValueWithDefault(
        PersistentMapInterface $binding,
        float|bool|int|string|TypeInterface|null $key,
        float|bool|int|string|TypeInterface|null $default,
    ): PersistentListInterface {
        $containsCheck = Phel::list([
            Symbol::create('contains?')->copyLocationFrom($binding),
            $this->mapSymbol,
            $key,
        ])->copyLocationFrom($binding);

        return Phel::list([
            Symbol::create(Symbol::NAME_IF)->copyLocationFrom($binding),
            $containsCheck,
            $this->createAccessValue($binding, $key),
            $default,
        ])->copyLocationFrom($binding);
    }
}
