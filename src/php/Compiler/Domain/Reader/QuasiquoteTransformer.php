<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Reader\Exceptions\SpliceNotInListException;
use Phel\Lang\Collections\LinkedList\PersistentList;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

use function count;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function str_ends_with;
use function substr;
use function trigger_error;

use const E_USER_DEPRECATED;

final readonly class QuasiquoteTransformer implements QuasiquoteTransformerInterface
{
    public function __construct(
        private GlobalEnvironmentInterface $env,
    ) {}

    /**
     * @param bool|float|int|string|TypeInterface|null $form The form to quasiqoute
     *
     * @throws SpliceNotInListException
     */
    public function transform($form): TypeInterface|string|float|int|bool|null
    {
        $context = new GensymContext();

        return $this->doTransform($form, $context);
    }

    /**
     * @throws SpliceNotInListException
     */
    private function doTransform(TypeInterface|string|float|int|bool|null $form, GensymContext $context): TypeInterface|string|float|int|bool|null
    {
        if ($this->isUnquote($form)) {
            /** @var PersistentList<mixed> $unquoteForm */
            $unquoteForm = $form;
            /** @var bool|float|int|string|TypeInterface|null $unquoted */
            $unquoted = $unquoteForm->get(1);

            return $unquoted;
        }

        if ($this->isUnquoteSplicing($form)) {
            throw new SpliceNotInListException();
        }

        if ($form instanceof PersistentList && count($form) > 0) {
            return $this->createFromPersistentList($form, $context);
        }

        if ($form instanceof PersistentVector && count($form) > 0) {
            return $this->createFromPersistentVector($form, $context);
        }

        if ($form instanceof PersistentMapInterface && count($form) > 0) {
            return $this->createFromMap($form, $context);
        }

        if ($this->isLiteral($form)) {
            return $form;
        }

        return $this->createOtherwise($form, $context);
    }

    /**
     * @param bool|float|int|string|TypeInterface|null $form
     */
    private function isUnquote($form): bool
    {
        return $form instanceof PersistentListInterface && count($form) > 0 && $form->get(0) == Symbol::NAME_UNQUOTE;
    }

    /**
     * @param bool|float|int|string|TypeInterface|null $form
     */
    private function isUnquoteSplicing($form): bool
    {
        return $form instanceof PersistentListInterface && count($form) > 0 && $form->get(0) == Symbol::NAME_UNQUOTE_SPLICING;
    }

    /**
     * @param PersistentList<mixed> $form
     *
     * @return PersistentListInterface<mixed>
     */
    private function createFromPersistentList(PersistentList $form, GensymContext $context): PersistentListInterface
    {
        return Phel::list([
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create(Symbol::NAME_LIST))->copyLocationFrom($form),
            Phel::list([
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($form, $context),
            ])->copyLocationFrom($form),
        ])->copyLocationFrom($form);
    }

    /**
     * @param PersistentVector<mixed> $form
     *
     * @return PersistentListInterface<mixed>
     */
    private function createFromPersistentVector(PersistentVector $form, GensymContext $context): PersistentListInterface
    {
        return Phel::list([
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create(Symbol::NAME_VECTOR))->copyLocationFrom($form),
            Phel::list([
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($form, $context),
            ])->copyLocationFrom($form),
        ])->copyLocationFrom($form);
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $form
     *
     * @return PersistentListInterface<mixed>
     */
    private function createFromMap(PersistentMapInterface $form, GensymContext $context): PersistentListInterface
    {
        $kvs = [];
        foreach ($form as $k => $v) {
            $kvs[] = $k;
            $kvs[] = $v;
        }

        return Phel::list([
            (Symbol::create(Symbol::NAME_APPLY))->copyLocationFrom($form),
            (Symbol::create(Symbol::NAME_MAP))->copyLocationFrom($form),
            Phel::list([
                (Symbol::create(Symbol::NAME_CONCAT))->copyLocationFrom($form),
                ...$this->expandList($kvs, $context),
            ])->copyLocationFrom($form),
        ])->copyLocationFrom($form);
    }

    /**
     * @param iterable<mixed> $seq
     *
     * @return array<int, mixed>
     */
    private function expandList(iterable $seq, GensymContext $context): array
    {
        $xs = [];
        foreach ($seq as $item) {
            /** @var bool|float|int|string|TypeInterface|null $item */
            if ($this->isUnquote($item)) {
                /** @var PersistentListInterface<mixed> $item */
                $xs[] = Phel::list([
                    (Symbol::create(Symbol::NAME_LIST))->copyLocationFrom($item),
                    $item->get(1),
                ])->copyLocationFrom($item);
            } elseif ($this->isUnquoteSplicing($item)) {
                /** @var PersistentListInterface<mixed> $item */
                $xs[] = $item->get(1);
            } else {
                $xs[] = Phel::list([
                    (Symbol::create(Symbol::NAME_LIST))->copyLocationFrom($item),
                    $this->doTransform($item, $context),
                ])->copyLocationFrom($item);
            }
        }

        return $xs;
    }

    /**
     * @param bool|float|int|string|TypeInterface|null $x The form to check
     */
    private function isLiteral(bool|float|int|TypeInterface|string|null $x): bool
    {
        return is_string($x)
            || is_float($x)
            || is_int($x)
            || is_bool($x)
            || !$x instanceof TypeInterface
            || $x instanceof Keyword;
    }

    private function createOtherwise(bool|float|int|TypeInterface|string|null $form, GensymContext $context): PersistentListInterface|TypeInterface
    {
        if ($form instanceof Symbol) {
            $name = $form->getFullName();
            if ($this->isAutoGensymSymbol($name)) {
                $this->warnIfDollarAutoGensym($form, $name);

                $base = substr($name, 0, -1) . '__';
                $sym = $context->getSymbolOrCreate($base);

                return Phel::list([
                    (Symbol::create(Symbol::NAME_QUOTE))->copyLocationFrom($form),
                    $sym->copyLocationFrom($form),
                ])->copyLocationFrom($form);
            }

            $node = $this->env->resolve($form, NodeEnvironment::empty());

            if ($node instanceof GlobalVarNode) {
                $form = Symbol::createForNamespace($node->getNamespace(), $form->getName())
                    ->copyLocationFrom($form);
            }
        }

        return Phel::list([
            (Symbol::create(Symbol::NAME_QUOTE))->copyLocationFrom($form),
            $form,
        ])->copyLocationFrom($form);
    }

    private function isAutoGensymSymbol(string $name): bool
    {
        return str_ends_with($name, Symbol::NAME_DOLLAR)
            || str_ends_with($name, Symbol::NAME_HASH);
    }

    private function warnIfDollarAutoGensym(Symbol $form, string $name): void
    {
        if (!str_ends_with($name, Symbol::NAME_DOLLAR)) {
            return;
        }

        $location = $form->getStartLocation();
        $suggested = substr($name, 0, -1) . Symbol::NAME_HASH;
        $where = $location instanceof SourceLocation
            ? sprintf(' (at %s:%d:%d)', $location->getFile(), $location->getLine(), $location->getColumn())
            : '';

        @trigger_error(
            sprintf('Using "%s" auto-gensym suffix is deprecated, use "%s" instead%s', $name, $suggested, $where),
            E_USER_DEPRECATED,
        );
    }
}
