<?php

namespace Phel;

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
            $this->processTuple($bindings, $binding, $value);
        }
    }

    public function processSymbol(array &$bindings, Symbol $binding, $value) {
        $bindings[] = [$binding, $value];
    }

    private function processTuple(array &$bindings, $b, $value) {
        $arrSymbol = Symbol::gen();
        $bindings[] = [$arrSymbol, $value];
        $state = 'start';

        for ($i = 0; $i < count($b); $i++) {
            $current = $b[$i];
            switch ($state) {
                case 'start':
                    if ($current instanceof Symbol && $current->getName() == '&') {
                        $state = 'rest';
                    } else if (!($current instanceof Symbol && $current->getName() == '_')) {
                        $accessSym = Symbol::gen();
                        $accessValue = Tuple::create(new Symbol('php/aget'), $arrSymbol, $i);
                        $bindings[] = [$accessSym, $accessValue];
        
                        $this->destructure($bindings, $current, $accessSym);
                    }
                    break;
                case 'rest':
                    $state = 'done';
                    if (!($current instanceof Symbol && $current->getName() == '_')) {
                        $accessSym = Symbol::gen();
                        $accessValue = Tuple::create(new Symbol('drop-destructure'), $i - 1, $arrSymbol);
                        $bindings[] = [$accessSym, $accessValue];

                        $this->destructure($bindings, $current, $accessSym);
                    }
                    break;
                case 'done':
                    throw new \Exception('Unsupported binding form, only one symbol can follow the & parameter');
            }
            
        }
    }
}