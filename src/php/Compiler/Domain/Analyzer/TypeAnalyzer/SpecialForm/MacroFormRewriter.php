<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function count;
use function is_string;

/**
 * Rewrites a `def`'s `(fn ...)` init expression before analysis. Two
 * transforms share the same single-/multi-arity dispatch:
 *
 *   - {@see self::injectImplicitParams()} prepends `&form`/`&env` to each
 *     arity's param vector for macro defs.
 *   - {@see self::injectReturnTypeFromMeta()} splices the def's `:tag`
 *     (e.g. `(defn ^int add [x y] ...)`) onto each arity's param vector so
 *     `FnSymbol` picks it up as the compiled signature's return type.
 *
 * Both are idempotent / Clojure-style: a vector that already declares the
 * implicit params or its own `:tag` is left untouched (more local wins).
 */
final readonly class MacroFormRewriter
{
    /**
     * Prepends `&form` and `&env` to each arity's parameter vector.
     * Supports single-arity and multi-arity fn forms. Returns the init
     * unchanged if it is not a `(fn ...)` list or if the implicit params
     * are already present.
     */
    public function injectImplicitParams(mixed $init): mixed
    {
        return $this->rewriteFnParamVectors($init, $this->prependImplicitParams(...));
    }

    /**
     * When the def's metadata carries `:tag`, splices that tag onto each
     * fn arity's param vector. Skips arities that already declare their
     * own vector `:tag`.
     *
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    public function injectReturnTypeFromMeta(mixed $init, PersistentMapInterface $meta): mixed
    {
        $tag = $meta->find(Keyword::create('tag'));
        if (!($tag instanceof Symbol) && !is_string($tag)) {
            return $init;
        }

        return $this->rewriteFnParamVectors(
            $init,
            fn(PersistentVectorInterface $params): PersistentVectorInterface => $this->withReturnTypeTag($params, $tag),
        );
    }

    /**
     * Dispatches a `(fn ...)` init to the single- or multi-arity rewriter,
     * applying `$transform` to each arity's leading param vector. Returns
     * the init unchanged when it is not a `(fn ...)` list.
     *
     * @param callable(PersistentVectorInterface<mixed>): PersistentVectorInterface<mixed> $transform
     */
    private function rewriteFnParamVectors(mixed $init, callable $transform): mixed
    {
        if (!$init instanceof PersistentListInterface) {
            return $init;
        }

        $head = $init->first();
        if (!$head instanceof Symbol || $head->getName() !== Symbol::NAME_FN) {
            return $init;
        }

        $second = $init->get(1);
        if ($second instanceof PersistentVectorInterface) {
            return $this->rewriteSingleArity($init, $transform);
        }

        if ($second instanceof PersistentListInterface) {
            return $this->rewriteMultiArity($init, $transform);
        }

        return $init;
    }

    /**
     * @param PersistentListInterface<mixed>                                               $fnList
     * @param callable(PersistentVectorInterface<mixed>): PersistentVectorInterface<mixed> $transform
     *
     * @return PersistentListInterface<mixed>
     */
    private function rewriteSingleArity(PersistentListInterface $fnList, callable $transform): PersistentListInterface
    {
        $items = $fnList->toArray();
        /** @var PersistentVectorInterface<mixed> $params */
        $params = $items[1];
        $items[1] = $transform($params);

        return Phel::list($items)->copyLocationFrom($fnList);
    }

    /**
     * @param PersistentListInterface<mixed>                                               $fnList
     * @param callable(PersistentVectorInterface<mixed>): PersistentVectorInterface<mixed> $transform
     *
     * @return PersistentListInterface<mixed>
     */
    private function rewriteMultiArity(PersistentListInterface $fnList, callable $transform): PersistentListInterface
    {
        $items = $fnList->toArray();
        for ($i = 1, $n = count($items); $i < $n; ++$i) {
            $arity = $items[$i];
            if (!$arity instanceof PersistentListInterface) {
                continue;
            }

            $arityItems = $arity->toArray();
            if ($arityItems === []) {
                continue;
            }

            if (!$arityItems[0] instanceof PersistentVectorInterface) {
                continue;
            }

            $arityItems[0] = $transform($arityItems[0]);
            $items[$i] = Phel::list($arityItems)->copyLocationFrom($arity);
        }

        return Phel::list($items)->copyLocationFrom($fnList);
    }

    /**
     * @param PersistentVectorInterface<mixed> $params
     *
     * @return PersistentVectorInterface<mixed>
     */
    private function withReturnTypeTag(PersistentVectorInterface $params, mixed $tag): PersistentVectorInterface
    {
        $existing = $params->getMeta();
        if ($existing instanceof PersistentMapInterface
            && $existing->find(Keyword::create('tag')) !== null
        ) {
            return $params;
        }

        $merged = ($existing ?? Phel::map())->put(Keyword::create('tag'), $tag);

        return $params->withMeta($merged);
    }

    /**
     * @param PersistentVectorInterface<mixed> $params
     *
     * @return PersistentVectorInterface<mixed>
     */
    private function prependImplicitParams(PersistentVectorInterface $params): PersistentVectorInterface
    {
        // Idempotency / shadowing guard: if the first two params are already `&form` and `&env`,
        // leave the vector untouched so users can explicitly shadow.
        if (count($params) >= 2) {
            $first = $params->get(0);
            $second = $params->get(1);
            if (
                $first instanceof Symbol && $first->getName() === '&form'
                && $second instanceof Symbol && $second->getName() === '&env'
            ) {
                return $params;
            }
        }

        $formSymbol = Symbol::create('&form')->copyLocationFrom($params);
        $envSymbol = Symbol::create('&env')->copyLocationFrom($params);

        return Phel::vector([$formSymbol, $envSymbol, ...$params->toArray()])
            ->copyLocationFrom($params);
    }
}
