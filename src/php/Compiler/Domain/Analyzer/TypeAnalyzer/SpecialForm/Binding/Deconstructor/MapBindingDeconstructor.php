<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Printer\Printer;

use function array_key_exists;
use function sprintf;

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
     * @param list<array{0: Symbol, 1: mixed}>         $bindings
     * @param PersistentMapInterface<mixed, mixed>     $binding  The binding form
     * @param bool|float|int|string|TypeInterface|null $value    The value form
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
                $this->assertVectorOfSymbols($binding, $bindTo, ':keys');
                $keys = $bindTo;
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'strs') {
                $this->assertVectorOfSymbols($binding, $bindTo, ':strs');
                $strs = $bindTo;
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'syms') {
                $this->assertVectorOfSymbols($binding, $bindTo, ':syms');
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

            $this->rejectReversedPair($binding, $key, $bindTo);
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

    /**
     * @param list<array{0: Symbol, 1: mixed}>     $bindings
     * @param PersistentMapInterface<mixed, mixed> $binding
     */
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

    /**
     * @param PersistentMapInterface<mixed, mixed> $binding
     *
     * @return PersistentListInterface<mixed>
     */
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
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     *
     * @return PersistentListInterface<mixed>
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

    /**
     * Catches Clojure-style `{local :keyword}` pairs at the top of map
     * destructuring. Phel writes them the other way (`{:keyword local}`),
     * so a `(Symbol, Keyword)` ordering is almost always a reflex typo
     * from a Clojure user. Surface a targeted "did you mean" suggestion
     * before downstream code blows up with the opaque
     * "Cannot destructure Phel\\Lang\\Keyword".
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     */
    private function rejectReversedPair(
        PersistentMapInterface $binding,
        mixed $key,
        mixed $bindTo,
    ): void {
        if (!$key instanceof Symbol || !$bindTo instanceof Keyword) {
            return;
        }

        $flipped = Phel::map($bindTo, $key);
        $message = sprintf(
            'Cannot destructure: expected map destructure pattern {:keyword local}, '
            . "got reversed pair starting with symbol '%s'.\n\n"
            . "Did you mean:\n  %s\n\n"
            . "(Phel's destructure order is :keyword first, then local — "
            . "opposite of Clojure's {local :keyword}.)",
            $key->getName(),
            Printer::readable()->print($flipped),
        );

        throw AnalyzerException::withLocation($message, $binding);
    }

    /**
     * `:keys`, `:strs`, `:syms` each take a vector of symbols. Anything
     * else is rejected here with a one-line shape error rather than
     * being silently dropped further down the deconstructor.
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     */
    private function assertVectorOfSymbols(
        PersistentMapInterface $binding,
        mixed $bindTo,
        string $directive,
    ): void {
        if (!$bindTo instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation(
                sprintf('`{%s [...]}` expects a vector of symbols', $directive),
                $binding,
            );
        }
    }
}
