<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader;

use Phel\Compiler\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use RuntimeException;

final class QuasiquoteTransformer implements QuasiquoteTransformerInterface
{
    private GlobalEnvironmentInterface $env;

    public function __construct(GlobalEnvironmentInterface $env)
    {
        $this->env = $env;
    }

    /**
     * @param AbstractType|string|float|int|bool|null $form The form to quasiqoute
     *
     * @return AbstractType|string|float|int|bool|null
     */
    public function transform($form)
    {
        if ($this->isUnquote($form)) {
            /** @var Tuple $form */
            return $form[1];
        }

        if ($this->isUnquoteSplicing($form)) {
            throw new RuntimeException('splice not in list');
        }

        if ($form instanceof Tuple && count($form) > 0) {
            return $this->createTupleFromTuple($form);
        }

        if ($form instanceof Table && count($form) > 0) {
            return $this->createTupleFromTable($form);
        }

        if ($form instanceof PhelArray && count($form) > 0) {
            return $this->createTupleFromPhelArray($form);
        }

        if ($this->isLiteral($form)) {
            return $form;
        }

        return $this->createTupleOtherwise($form);
    }

    /**
     * @param AbstractType|string|float|int|bool|null $form
     */
    private function isUnquote($form): bool
    {
        return $form instanceof Tuple && $form[0] == Symbol::NAME_UNQUOTE;
    }

    /**
     * @param AbstractType|string|float|int|bool|null $form
     */
    private function isUnquoteSplicing($form): bool
    {
        return $form instanceof Tuple && $form[0] == Symbol::NAME_UNQUOTE_SPLICING;
    }

    private function createTupleFromTuple(Tuple $form): Tuple
    {
        $tupleBuilder = $form->isUsingBracket()
            ? Symbol::NAME_TUPLE_BRACKETS
            : Symbol::NAME_TUPLE;

        return Tuple::create(
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create($tupleBuilder))->copyLocationFrom($form),
            Tuple::create(
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($form)
            )->copyLocationFrom($form)
        )->copyLocationFrom($form);
    }

    private function createTupleFromTable(Table $form): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create(Symbol::NAME_TABLE))->copyLocationFrom($form),
            Tuple::create(
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($form->toKeyValueList())
            )->copyLocationFrom($form)
        )->copyLocationFrom($form);
    }

    private function createTupleFromPhelArray(PhelArray $form): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create(Symbol::NAME_ARRAY))->copyLocationFrom($form),
            Tuple::create(
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($form)
            )->copyLocationFrom($form)
        )->copyLocationFrom($form);
    }

    private function expandList(iterable $seq): array
    {
        $xs = [];
        foreach ($seq as $item) {
            if ($this->isUnquote($item)) {
                $xs[] = Tuple::create(
                    (Symbol::create(Symbol::NAME_TUPLE))->copyLocationFrom($item),
                    $item[1]
                )->copyLocationFrom($item);
            } elseif ($this->isUnquoteSplicing($item)) {
                $xs[] = $item[1];
            } else {
                $xs[] = Tuple::create(
                    (Symbol::create(Symbol::NAME_TUPLE))->copyLocationFrom($item),
                    $this->transform($item)
                )->copyLocationFrom($item);
            }
        }

        return $xs;
    }

    /**
     * @param AbstractType|string|float|int|bool|null $x The form to check
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

    /**
     * @param AbstractType|string|float|int|bool|null $form
     */
    private function createTupleOtherwise($form): Tuple
    {
        if ($form instanceof Symbol) {
            $node = $this->env->resolve($form, NodeEnvironment::empty());

            if ($node instanceof GlobalVarNode) {
                $form = Symbol::createForNamespace($node->getNamespace(), $form->getName())
                    ->copyLocationFrom($form);
            }
        }

        return Tuple::create(
            (Symbol::create(Symbol::NAME_QUOTE))->copyLocationFrom($form),
            $form
        )->copyLocationFrom($form);
    }
}
