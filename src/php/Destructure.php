<?php

namespace Phel;

use Exception;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

class Destructure
{
    public function run(Tuple $x): array
    {
        $bindings = [];

        for ($i = 0; $i < count($x); $i+=2) {
            $this->destructure($bindings, $x[$i], $x[$i+1]);
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
            if (is_object($form)) {
                $type = get_class($form);
            } else {
                $type = gettype($form);
            }

            if ($form instanceof AbstractType) {
                throw new AnalyzerException(
                    'Can not destructure ' . $type,
                    $form->getStartLocation(),
                    $form->getEndLocation()
                );
            } else {
                throw new AnalyzerException('Can not destructure ' . $type);
            }
        }
    }

    /**
     * @param array $bindings
     * @param AbstractType|scalar|null $binding
     * @param mixed $value
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
     * @param array $bindings
     * @param Table $b
     * @param mixed $value
     */
    private function processTable(array &$bindings, Table $b, $value): void
    {
        $tableSymbol = Symbol::gen()->copyLocationFrom($b);
        $bindings[] = [$tableSymbol, $value];

        foreach ($b as $key => $bindTo) {
            $accessSym = Symbol::gen()->copyLocationFrom($b);
            $accessValue = Tuple::create(
                (Symbol::create('php/aget'))->copyLocationFrom($b),
                $tableSymbol,
                $key
            )->copyLocationFrom($b);
            $bindings[] = [$accessSym, $accessValue];

            $this->destructure($bindings, $bindTo, $accessSym);
        }
    }

    /**
     * @param array $bindings
     * @param PhelArray $b
     * @param mixed $value
     */
    private function processArray(array &$bindings, PhelArray $b, $value): void
    {
        $arrSymbol = Symbol::gen()->copyLocationFrom($b);
        $bindings[] = [$arrSymbol, $value];

        for ($i = 0; $i < count($b); $i+=2) {
            $index = $b[$i];
            $bindTo = $b[$i+1];

            $accessSym = Symbol::gen()->copyLocationFrom($b);
            $accessValue = Tuple::create(
                (Symbol::create('php/aget'))->copyLocationFrom($b),
                $arrSymbol,
                $index
            )->copyLocationFrom($b);
            $bindings[] = [$accessSym, $accessValue];

            $this->destructure($bindings, $bindTo, $accessSym);
        }
    }

    /**
     * @param array $bindings
     * @param Symbol $b
     * @param mixed $value
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
     * @param array $bindings
     * @param Tuple $b
     * @param mixed $value
     */
    private function processTuple(array &$bindings, Tuple $b, $value): void
    {
        $arrSymbol = Symbol::gen()->copyLocationFrom($b);

        $bindings[] = [$arrSymbol, $value];
        $lastListSym = $arrSymbol;
        $state = 'start';

        for ($i = 0; $i < count($b); $i++) {
            $current = $b[$i];
            switch ($state) {
                case 'start':
                    if ($current instanceof Symbol && $current->getName() == '&') {
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
                    throw new AnalyzerException(
                        'Unsupported binding form, only one symbol can follow the & parameter',
                        $b->getStartLocation(),
                        $b->getEndLocation()
                    );
            }
        }
    }
}
