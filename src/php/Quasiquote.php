<?php

declare(strict_types=1);

namespace Phel;

use Phel\Ast\GlobalVarNode;
use Phel\Lang\Keyword;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

final class Quasiquote
{
    private GlobalEnvironment $env;

    public function __construct(GlobalEnvironment $env)
    {
        $this->env = $env;
    }

    /**
     * @param AbstractType|scalar|null $form The form to quasiqoute
     *
     * @return AbstractType|scalar|null
     */
    public function quasiquote($form)
    {
        if ($this->isUnquote($form)) {
            /** @var Tuple $form */
            return $form[1];
        }

        if ($this->isUnquoteSplicing($form)) {
            throw new \Exception('splice not in list');
        }

        if ($form instanceof Tuple && count($form) > 0) {
            $tupleBuilder = $form->isUsingBracket() ? 'tuple-brackets' : 'tuple';

            return Tuple::create(
                (Symbol::create('apply'))->copyLocationFrom($form),
                (Symbol::create($tupleBuilder))->copyLocationFrom($form),
                Tuple::create(
                    (Symbol::create('concat'))->copyLocationFrom($form),
                    ...$this->expandList($form)
                )->copyLocationFrom($form)
            )->copyLocationFrom($form);
        }

        if ($form instanceof Table && count($form) > 0) {
            return Tuple::create(
                (Symbol::create('apply'))->copyLocationFrom($form),
                (Symbol::create('table'))->copyLocationFrom($form),
                Tuple::create(
                    (Symbol::create('concat'))->copyLocationFrom($form),
                    ...$this->expandList($form->toKeyValueList())
                )->copyLocationFrom($form)
            )->copyLocationFrom($form);
        }

        if ($form instanceof PhelArray && count($form) > 0) {
            return Tuple::create(
                (Symbol::create('apply'))->copyLocationFrom($form),
                (Symbol::create('array'))->copyLocationFrom($form),
                Tuple::create(
                    (Symbol::create('concat'))->copyLocationFrom($form),
                    ...$this->expandList($form)
                )->copyLocationFrom($form)
            )->copyLocationFrom($form);
        }

        if ($this->isLiteral($form)) {
            return $form;
        }


        if ($form instanceof Symbol) {
            $node = $this->env->resolve($form, NodeEnvironment::empty());

            if ($node instanceof GlobalVarNode) {
                $form = Symbol::createForNamespace($node->getNamespace(), $form->getName())->copyLocationFrom($form);
            }
        }

        return Tuple::create(
            (Symbol::create('quote'))->copyLocationFrom($form),
            $form
        )->copyLocationFrom($form);
    }

    /**
     * @param AbstractType|scalar|null $form The form to quasiqoute
     */
    private function isUnquote($form): bool
    {
        return $form instanceof Tuple && $form[0] == 'unquote';
    }

    /**
     * @param AbstractType|scalar|null $form The form to quasiqoute
     */
    private function isUnquoteSplicing($form): bool
    {
        return $form instanceof Tuple && $form[0] == 'unquote-splicing';
    }

    private function expandList(iterable $seq): array
    {
        $xs = [];
        foreach ($seq as $item) {
            if ($this->isUnquote($item)) {
                $xs[] = Tuple::create(
                    (Symbol::create('tuple'))->copyLocationFrom($item),
                    $item[1]
                )->copyLocationFrom($item);
            } elseif ($this->isUnquoteSplicing($item)) {
                $xs[] = $item[1];
            } else {
                $xs[] = Tuple::create(
                    (Symbol::create('tuple'))->copyLocationFrom($item),
                    $this->quasiquote($item)
                )->copyLocationFrom($item);
            }
        }

        return $xs;
    }

    /**
     * @param AbstractType|scalar|null $x The form to check.
     */
    private function isLiteral($x): bool
    {
        return is_string($x)
            || is_float($x)
            || is_int($x)
            || is_bool($x)
            || $x === null
            || $x instanceof Keyword;
    }
}
