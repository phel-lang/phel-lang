<?php

namespace Phel;

use Phel\Ast\BindingNode;
use Phel\Lang\Boolean;
use Phel\Lang\Keyword;
use Phel\Lang\Nil;
use Phel\Lang\Number;
use Phel\Lang\Phel;
use Phel\Lang\PhelString;
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

    private function destructure(array &$bindings, Phel $binding, Phel $value) {
        if ($binding instanceof Symbol) {
            $this->processSymbol($bindings, $binding, $value);
        } else if ($binding instanceof Tuple) {
            $this->processTuple($bindings, $binding, $value);
        }
    }

    public function processSymbol(array &$bindings, Symbol $binding, Phel $value) {
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
                    if ($current == new Symbol('&')) {
                        $state = 'rest';
                    } else if ($current != new Symbol('_')) {
                        $accessSym = Symbol::gen();
                        $accessValue = Tuple::create(new Symbol('php/aget'), $arrSymbol, new Number($i));
                        $bindings[] = [$accessSym, $accessValue];
        
                        $this->destructure($bindings, $current, $accessSym);
                    }
                    break;
                case 'rest':
                    $state = 'done';
                    if ($current != new Symbol('_')) {
                        $accessSym = Symbol::gen();
                        $accessValue = Tuple::create(new Symbol('drop-destructure'), new Number($i - 1), $arrSymbol);
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