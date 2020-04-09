<?php

namespace Phel;

use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

class Quasiquote {

    private function isLiteral($x) {
        return is_string($x) 
          || is_float($x)
          || is_int($x)
          || is_bool($x)
          || $x === null
          || $x instanceof Keyword
          || $x instanceof PhelArray
          || $x instanceof Table;
    }

    public function quasiquote($form) {
        if ($this->isUnquote($form)) {
            return $form[1];
        } else if ($this->isUnquoteSplicing($form)) {
            throw new \Exception('splice not in list');
        } else if ($form instanceof Tuple && count($form) > 0) {
            return Tuple::create(new Symbol('apply'), new Symbol('tuple'), Tuple::create(new Symbol('concat'), ...$this->expandList($form)));
            // TODO: Handle Table
            // TODO: Handle Array
        } else if ($this->isLiteral($form)) {
            return $form;
        } else {
            return Tuple::create(new Symbol('quote'), $form);
        }
    }

    private function expandList($seq) {
        $xs = [];
        foreach ($seq as $item) {
            if ($this->isUnquote($item)) {
                $xs[] = Tuple::create(new Symbol('tuple'), $item[1]);
            } else if ($this->isUnquoteSplicing($item)) {
                $xs[] = $item[1];
            } else {
                $xs[] = Tuple::create(new Symbol('tuple'), $this->quasiquote($item));
            }
        }

        return $xs;
    }

    private function isUnquote($form) {
        return $form instanceof Tuple && $form[0] == 'unquote';
    }

    private function isUnquoteSplicing($form) {
        return $form instanceof Tuple && $form[0] == 'unquote-splicing';
    }
}