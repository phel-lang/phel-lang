<?php

namespace Phel;

use Phel\Lang\Boolean;
use Phel\Lang\Keyword;
use Phel\Lang\Nil;
use Phel\Lang\Number;
use Phel\Lang\Phel;
use Phel\Lang\PhelString;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

class Quasiquote {


    public function quasiquote(Phel $form) {
        if ($this->isUnquote($form)) {
            return $form[1];
        } else if ($this->isUnquoteSplicing($form)) {
            throw new \Exception('splice not in list');
        } else if ($form instanceof Tuple && count($form) > 0) {
            return Tuple::create(new Symbol('apply'), new Symbol('tuple'), Tuple::create(new Symbol('concat'), ...$this->expandList($form)));
            // TODO: Handle Table
            // TODO: Handle Array
        } else if ($form instanceof Keyword || $form instanceof Number || $form instanceof PhelString || $form instanceof Boolean || $form instanceof Nil) {
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