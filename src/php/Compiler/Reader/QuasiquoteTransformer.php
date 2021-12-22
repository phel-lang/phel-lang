<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader;

use Phel\Compiler\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Reader\Exceptions\SpliceNotInListException;
use Phel\Lang\Collections\LinkedList\PersistentList;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;

final class QuasiquoteTransformer implements QuasiquoteTransformerInterface
{
    private GlobalEnvironmentInterface $env;

    public function __construct(GlobalEnvironmentInterface $env)
    {
        $this->env = $env;
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $form The form to quasiqoute
     *
     * @throws SpliceNotInListException
     *
     * @return TypeInterface|string|float|int|bool|null
     */
    public function transform($form)
    {
        if ($this->isUnquote($form)) {
            /** @var PersistentList $form */
            return $form->get(1);
        }

        if ($this->isUnquoteSplicing($form)) {
            throw new SpliceNotInListException();
        }

        if ($form instanceof PersistentList && count($form) > 0) {
            return $this->createFromPersistentList($form);
        }

        if ($form instanceof PersistentVector && count($form) > 0) {
            return $this->createFromPersistentVector($form);
        }

        if ($form instanceof PersistentMapInterface && count($form) > 0) {
            return $this->createFromMap($form);
        }

        if ($this->isLiteral($form)) {
            return $form;
        }

        return $this->createOtherwise($form);
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $form
     */
    private function isUnquote($form): bool
    {
        return $form instanceof PersistentListInterface && $form->get(0) == Symbol::NAME_UNQUOTE;
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $form
     */
    private function isUnquoteSplicing($form): bool
    {
        return $form instanceof PersistentListInterface && $form->get(0) == Symbol::NAME_UNQUOTE_SPLICING;
    }

    private function createFromPersistentList(PersistentList $form): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create(Symbol::NAME_LIST))->copyLocationFrom($form),
            TypeFactory::getInstance()->persistentListFromArray([
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($form),
            ])->copyLocationFrom($form),
        ])->copyLocationFrom($form);
    }

    private function createFromPersistentVector(PersistentVector $form): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create(Symbol::NAME_VECTOR))->copyLocationFrom($form),
            TypeFactory::getInstance()->persistentListFromArray([
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($form),
            ])->copyLocationFrom($form),
        ])->copyLocationFrom($form);
    }

    private function createFromMap(PersistentMapInterface $form): PersistentListInterface
    {
        $kvs = [];
        foreach ($form as $k => $v) {
            $kvs[] = $k;
            $kvs[] = $v;
        }

        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create(Symbol::NAME_MAP))->copyLocationFrom($form),
            TypeFactory::getInstance()->persistentListFromArray([
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($kvs),
            ])->copyLocationFrom($form),
        ])->copyLocationFrom($form);
    }

    /**
     * @return array<int, mixed>
     */
    private function expandList(iterable $seq): array
    {
        $xs = [];
        foreach ($seq as $item) {
            if ($this->isUnquote($item)) {
                $xs[] = TypeFactory::getInstance()->persistentListFromArray([
                    (Symbol::create(Symbol::NAME_LIST))->copyLocationFrom($item),
                    $item->get(1),
                ])->copyLocationFrom($item);
            } elseif ($this->isUnquoteSplicing($item)) {
                $xs[] = $item->get(1);
            } else {
                $xs[] = TypeFactory::getInstance()->persistentListFromArray([
                    (Symbol::create(Symbol::NAME_LIST))->copyLocationFrom($item),
                    $this->transform($item),
                ])->copyLocationFrom($item);
            }
        }

        return $xs;
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $x The form to check
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
     * @param TypeInterface|string|float|int|bool|null $form
     */
    private function createOtherwise($form): PersistentListInterface
    {
        if ($form instanceof Symbol) {
            $node = $this->env->resolve($form, NodeEnvironment::empty());

            if ($node instanceof GlobalVarNode) {
                $form = Symbol::createForNamespace($node->getNamespace(), $form->getName())
                    ->copyLocationFrom($form);
            }
        }

        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create(Symbol::NAME_QUOTE))->copyLocationFrom($form),
            $form,
        ])->copyLocationFrom($form);
    }
}
