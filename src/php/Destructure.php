<?php

namespace Phel;

use Exception;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Phel;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

class Destructure {

    public function run(Tuple $x) {
        $bindings = [];
        
        for ($i = 0; $i < count($x); $i+=2) {
            $this->destructure($bindings, $x[$i], $x[$i+1]);
        }

        return $bindings;
    }

    private function destructure(array &$bindings, $binding, $value) {
        if ($binding instanceof Symbol) {
            $this->processSymbol($bindings, $binding, $value);
        } else if ($binding instanceof Tuple) {
            if (count($binding) > 0 && $binding[0] == new Symbol('table')) {
                $this->processTable($bindings, $binding, $value);
            } else if (count($binding) > 0 && $binding[0] == new Symbol('array')) {
                $this->processArray($bindings, $binding, $value);
            } else {
                $this->processTuple($bindings, $binding, $value);
            }
        } else {
            $type = gettype($binding);
            if ($type == 'object') {
                $type = get_class($binding);
            }

            if ($binding instanceof Phel) {
                throw new AnalyzerException(
                    "Can not destructure " .  $type,
                    $binding->getStartLocation(),
                    $binding->getEndLocation()
                );
            } else {
                // TODO: How can we get start and end location here?
                throw new Exception("Can not destructure " .  $type);
            }
        }
    }

    private function processTable(array &$bindings, Tuple $b, $value) {
        $tableSymbol = Symbol::gen();
        $bindings[] = [$tableSymbol, $value];

        for ($i = 1; $i < count($b); $i+=2) {
            $key = $b[$i];
            $bindTo = $b[$i+1];

            $accessSym = Symbol::gen();
            $accessValue = Tuple::create(new Symbol('php/aget'), $tableSymbol, $key);
            $bindings[] = [$accessSym, $accessValue];

            $this->destructure($bindings, $bindTo, $accessSym);
        }
    }

    private function processArray(array &$bindings, $b, $value) {
        $arrSymbol = Symbol::gen();
        $bindings[] = [$arrSymbol, $value];

        for ($i = 1; $i < count($b); $i+=2) {
            $index = $b[$i];
            $bindTo = $b[$i+1];

            $accessSym = Symbol::gen();
            $accessValue = Tuple::create(new Symbol('php/aget'), $arrSymbol, $index);
            $bindings[] = [$accessSym, $accessValue];

            $this->destructure($bindings, $bindTo, $accessSym);
        }
    }

    private function processSymbol(array &$bindings, Symbol $binding, $value) {
        if ($binding->getName() !== "_") {
            $bindings[] = [$binding, $value];
        }
    }

    private function processTuple(array &$bindings, Tuple $b, $value) {
        $arrSymbol = Symbol::gen();
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
                        $accessSym = Symbol::gen();
                        $accessValue = Tuple::create(new Symbol('php/aget'), $lastListSym, 0);
                        $bindings[] = [$accessSym, $accessValue];

                        $nextSym = Symbol::gen();
                        $nextValue = Tuple::create(new Symbol('next'), $lastListSym);
                        $bindings[] = [$nextSym, $nextValue];
                        $lastListSym = $nextSym;
        
                        $this->destructure($bindings, $current, $accessSym);
                    }
                    break;
                case 'rest':
                    $state = 'done';
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