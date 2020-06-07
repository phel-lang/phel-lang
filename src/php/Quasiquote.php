<?php

namespace Phel;

use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

class Quasiquote
{

    /**
     * @param Phel|scalar|null $x The form to check.
     */
    private function isLiteral($x): bool
    {
        return is_string($x)
          || is_float($x)
          || is_int($x)
          || is_bool($x)
          || $x === null
          || $x instanceof Keyword
          || $x instanceof PhelArray
          || $x instanceof Table;
    }

    /**
     * @param Phel|scalar|null $form The form to quasiqoute
     *
     * @return Phel|scalar|null
     */
    public function quasiquote($form)
    {
        if ($this->isUnquote($form)) {
            /** @var Tuple $form */
            return $form[1];
        } elseif ($this->isUnquoteSplicing($form)) {
            throw new \Exception('splice not in list');
        } elseif ($form instanceof Tuple && count($form) > 0) {
            return Tuple::create(
                (new Symbol('apply'))->copyLocationFrom($form),
                (new Symbol('tuple'))->copyLocationFrom($form),
                Tuple::create(
                    (new Symbol('concat'))->copyLocationFrom($form),
                    ...$this->expandList($form)
                )->copyLocationFrom($form)
            )->copyLocationFrom($form);
        // TODO: Handle Table and Array
        } elseif ($this->isLiteral($form)) {
            return $form;
        } else {
            return Tuple::create(
                (new Symbol('quote'))->copyLocationFrom($form),
                $form
            )->copyLocationFrom($form);
        }
    }

    private function expandList(Tuple $seq): array
    {
        $xs = [];
        foreach ($seq as $item) {
            if ($this->isUnquote($item)) {
                $xs[] = Tuple::create(
                    (new Symbol('tuple'))->copyLocationFrom($item),
                    $item[1]
                )->copyLocationFrom($item);
            } elseif ($this->isUnquoteSplicing($item)) {
                $xs[] = $item[1];
            } else {
                $xs[] = Tuple::create(
                    (new Symbol('tuple'))->copyLocationFrom($item),
                    $this->quasiquote($item)
                )->copyLocationFrom($item);
            }
        }

        return $xs;
    }

    /**
     * @param Phel|scalar|null $form The form to quasiqoute
     *
     * @return bool
     */
    private function isUnquote($form)
    {
        return $form instanceof Tuple && $form[0] == 'unquote';
    }

    /**
     * @param Phel|scalar|null $form The form to quasiqoute
     *
     * @return bool
     */
    private function isUnquoteSplicing($form)
    {
        return $form instanceof Tuple && $form[0] == 'unquote-splicing';
    }
}
