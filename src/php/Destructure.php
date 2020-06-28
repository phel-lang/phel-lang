<?php

declare(strict_types=1);

namespace Phel;

use Exception;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

final class Destructure
{
    public function run(Tuple $tuple): array
    {
        $bindings = [];

        for ($i = 0, $iMax = count($tuple); $i < $iMax; $i += 2) {
            $this->destructure($bindings, $tuple[$i], $tuple[$i + 1]);
        }

        return $bindings;
    }

    /**
     * Checks if a binding form is valid.
     *
     * @psalm-assert !null $form
     *
     * @param mixed $form The form to check
     */
    public static function isSupportedBinding($form): bool
    {
        return (
            $form instanceof Symbol
            || $form instanceof Tuple
            || $form instanceof Table
            || $form instanceof PhelArray
        );
    }

    /**
     * Checks if a binding form is valid. If this is not the case an
     * AnalyzerException is thrown.
     *
     * @psalm-assert !null $form
     *
     * @param mixed $form The form to check
     *
     * @throws AnalyzerException
     */
    public static function assertSupportedBinding($form): void
    {
        if (!self::isSupportedBinding($form)) {
            $type = is_object($form) ? get_class($form) : gettype($form);

            if ($form instanceof AbstractType) {
                throw AnalyzerException::withLocation('Can not destructure ' . $type, $form);
            }

            throw new AnalyzerException('Can not destructure ' . $type);
        }
    }

    /**
     * Destructure a $binding $value pair and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param AbstractType|scalar|null $binding The binding form
     * @param AbstractType|scalar|null $value The value form
     */
    private function destructure(array &$bindings, $binding, $value): void
    {
        self::assertSupportedBinding($binding);

        if ($binding instanceof Symbol) {
            $this->processSymbol($bindings, $binding, $value);
        } elseif ($binding instanceof Tuple) {
            $this->processTuple($bindings, $binding, $value);
        } elseif ($binding instanceof Table) {
            $this->processTable($bindings, $binding, $value);
        } elseif ($binding instanceof PhelArray) {
            $this->processArray($bindings, $binding, $value);
        }
    }

    /**
     * Destructure a table $binding and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param Table $binding The binding form
     * @param AbstractType|scalar|null $value The value form
     */
    private function processTable(array &$bindings, Table $table, $value): void
    {
        $tableSymbol = Symbol::gen()->copyLocationFrom($table);
        $bindings[] = [$tableSymbol, $value];

        foreach ($table as $key => $bindTo) {
            $accessSym = Symbol::gen()->copyLocationFrom($table);
            $accessValue = Tuple::create(
                (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))->copyLocationFrom($table),
                $tableSymbol,
                $key
            )->copyLocationFrom($table);
            $bindings[] = [$accessSym, $accessValue];

            $this->destructure($bindings, $bindTo, $accessSym);
        }
    }

    /**
     * Destructure a array $binding and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param PhelArray $binding The binding form
     * @param AbstractType|scalar|null $value The value form
     */
    private function processArray(array &$bindings, PhelArray $phelArray, $value): void
    {
        $arrSymbol = Symbol::gen()->copyLocationFrom($phelArray);
        $bindings[] = [$arrSymbol, $value];

        for ($i = 0, $iMax = count($phelArray); $i < $iMax; $i += 2) {
            $index = $phelArray[$i];
            $bindTo = $phelArray[$i + 1];

            $accessSym = Symbol::gen()->copyLocationFrom($phelArray);
            $accessValue = Tuple::create(
                (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))->copyLocationFrom($phelArray),
                $arrSymbol,
                $index
            )->copyLocationFrom($phelArray);
            $bindings[] = [$accessSym, $accessValue];

            $this->destructure($bindings, $bindTo, $accessSym);
        }
    }

    /**
     * Destructure a symbol $binding and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param Symbol $binding The binding form
     * @param AbstractType|scalar|null $value The value form
     */
    private function processSymbol(array &$bindings, Symbol $binding, $value): void
    {
        if ($binding->getName() === '_') {
            $s = Symbol::gen()->copyLocationFrom($binding);
            $bindings[] = [$s, $value];
        } else {
            $bindings[] = [$binding, $value];
        }
    }

    /**
     * Destructure a tuple $binding and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param Tuple $binding The binding form
     * @param AbstractType|scalar|null $value The value form
     */
    private function processTuple(array &$bindings, Tuple $tuple, $value): void
    {
        $arrSymbol = Symbol::gen()->copyLocationFrom($tuple);

        $bindings[] = [$arrSymbol, $value];
        $lastListSym = $arrSymbol;
        $state = 'start';

        foreach ($tuple as $current) {
            switch ($state) {
                case 'start':
                    if ($current instanceof Symbol && $current->getName() === '&') {
                        $state = 'rest';
                    } else {
                        $accessSym = Symbol::gen()->copyLocationFrom($current);
                        $accessValue = Tuple::create(
                            (Symbol::create('first'))->copyLocationFrom($current),
                            $lastListSym
                        )->copyLocationFrom($current);
                        $bindings[] = [$accessSym, $accessValue];

                        $nextSym = Symbol::gen()->copyLocationFrom($current);
                        $nextValue = Tuple::create(
                            (Symbol::create('next'))->copyLocationFrom($current),
                            $lastListSym
                        )->copyLocationFrom($current);
                        $bindings[] = [$nextSym, $nextValue];
                        $lastListSym = $nextSym;

                        $this->destructure($bindings, $current, $accessSym);
                    }
                    break;
                case 'rest':
                    $state = 'done';
                    $accessSym = Symbol::gen()->copyLocationFrom($current);
                    $bindings[] = [$accessSym, $lastListSym];
                    $this->destructure($bindings, $current, $accessSym);
                    break;
                case 'done':
                    throw AnalyzerException::withLocation(
                        'Unsupported binding form, only one symbol can follow the & parameter',
                        $tuple
                    );
            }
        }
    }
}
