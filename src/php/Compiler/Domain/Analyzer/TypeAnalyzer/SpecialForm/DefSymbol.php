<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;

use function array_map;
use function array_pop;
use function array_slice;
use function assert;
use function count;
use function implode;
use function in_array;
use function is_scalar;
use function is_string;
use function max;

/**
 * (def name value).
 *
 * Defines a global variable in the current namespace.
 */
final class DefSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    private const array POSSIBLE_TUPLE_SIZES = [2, 3, 4];

    /** Number of implicit parameters (`&form` and `&env`) injected into macro fns. */
    private const int MACRO_IMPLICIT_PARAMS = 2;

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @throws AbstractLocatedException
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefNode
    {
        $this->verifySizeOfTuple($list);

        $nameSymbol = $list->get(1);
        if (!($nameSymbol instanceof Symbol)) {
            throw AnalyzerException::wrongArgumentType("First argument of 'def", 'Symbol', $nameSymbol, $list);
        }

        $namespace = $this->analyzer->getNamespace();

        $this->analyzer->addDefinition($namespace, $nameSymbol);

        [$metaMap, $init] = $this->createMetaMapAndInit($list);

        $isMacro = $metaMap[Keyword::create('macro')] === true;
        if ($isMacro) {
            $init = $this->injectMacroImplicitParams($init);
        }

        $init = $this->injectReturnTypeFromMeta($init, $metaMap);

        $initNode = $this->analyzeInit($init, $env, $namespace, $nameSymbol, $metaMap);
        $skip = $isMacro ? self::MACRO_IMPLICIT_PARAMS : 0;

        if ($initNode instanceof FnNode) {
            $initNode->markAsDefinition();
            // Macro args bind to raw Phel forms regardless of how the
            // body manipulates them, so a primitive-op observation in
            // the macro body must not type-narrow the runtime signature.
            if (!$isMacro) {
                $this->graftInferredParamTags($initNode, $skip);
            }

            $metaMap = $metaMap->put('min-arity', max(0, $initNode->getMinArity() - $skip));
            $metaMap = $metaMap->put('is-variadic', $initNode->isVariadic());
            $metaMap = $metaMap->put('arglists', $this->buildFnNodeArglist($initNode, $skip));
        } elseif ($initNode instanceof MultiFnNode) {
            $metaMap = $metaMap->put('min-arity', max(0, $initNode->getMinArity() - $skip));
            $metaMap = $metaMap->put('is-variadic', $initNode->isVariadic());
            $maxArity = $initNode->getMaxArity();
            $metaMap = $metaMap->put('max-arity', $maxArity === null ? null : max(0, $maxArity - $skip));
            $metaMap = $metaMap->put('arglists', $this->buildMultiFnNodeArglists($initNode, $skip));
        }

        $metaMap = $this->persistInferredReturnTag($metaMap, $this->inferredReturnTypeOf($initNode));

        // Stash a compile-time-only superset of the meta with `:param-tags`
        // attached so subsequent calls in the same compilation unit can
        // run static type checks against the def's declared param tags
        // without round-tripping through the runtime registry (which
        // `compile`-only flows never populate). The runtime `$meta`
        // built below intentionally omits `:param-tags` so the emitted
        // PHP keeps its existing shape.
        if ($initNode instanceof FnNode) {
            $paramTags = $this->buildParamTags($initNode, $skip);
            $compileTimeMeta = $metaMap->put(Keyword::create('param-tags'), $paramTags);
            $this->analyzer->setCompileTimeMeta($namespace, $nameSymbol, $compileTimeMeta);
        } else {
            $this->analyzer->setCompileTimeMeta($namespace, $nameSymbol, $metaMap);
        }

        $meta = $this->analyzer->analyze($metaMap, $env->withExpressionContext());
        assert($meta instanceof MapNode);

        return new DefNode(
            $env,
            $namespace,
            $nameSymbol,
            $meta,
            $initNode,
            $list->getStartLocation(),
        );
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function verifySizeOfTuple(PersistentListInterface $list): void
    {
        $listSize = count($list);

        if (!in_array($listSize, self::POSSIBLE_TUPLE_SIZES, true)) {
            throw AnalyzerException::withLocation(
                "Two or three arguments are required for 'def. Got " . $listSize,
                $list,
            );
        }
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return array{0:PersistentMapInterface<mixed, mixed>, 1:mixed}
     */
    private function createMetaMapAndInit(PersistentListInterface $list): array
    {
        [$meta, $init] = $this->getInitialMetaAndInit($list);

        if (!($init instanceof TypeInterface)
            && !is_scalar($init)
            && $init !== null
        ) {
            throw AnalyzerException::withLocation('$init must be TypeInterface|string|float|int|bool|null', $list);
        }

        $meta = $this->normalizeMeta($meta, $list);

        // `^:dynamic`, `^:private`, etc. attach to the name symbol
        // (`(def ^:dynamic *x* 1)`). Merge those flags into the def's
        // metadata map so runtime code can read them off the var.
        $nameMeta = $list->get(1)->getMeta();
        if ($nameMeta instanceof PersistentMapInterface) {
            foreach ($nameMeta->getIterator() as $key => $value) {
                if ($key !== null) {
                    $meta = $meta->put($key, $this->normalizeMetaValue($key, $value));
                }
            }
        }

        $listMeta = $list->getMeta();
        if ($listMeta instanceof PersistentMapInterface) {
            foreach ($listMeta->getIterator() as $key => $value) {
                if ($key !== null) {
                    $meta = $meta->put($key, $value);
                }
            }
        }

        $startLocation = $list->getStartLocation();
        if ($startLocation instanceof SourceLocation) {
            $meta = $meta->put(Keyword::create('start-location'), Phel::map(
                Keyword::create('file'),
                $startLocation->getFile(),
                Keyword::create('line'),
                $startLocation->getLine(),
                Keyword::create('column'),
                $startLocation->getColumn(),
            ));
        }

        $endLocation = $list->getEndLocation();
        if ($endLocation instanceof SourceLocation) {
            $meta = $meta->put(Keyword::create('end-location'), Phel::map(
                Keyword::create('file'),
                $endLocation->getFile(),
                Keyword::create('line'),
                $endLocation->getLine(),
                Keyword::create('column'),
                $endLocation->getColumn(),
            ));
        }

        return [$meta, $init];
    }

    /**
     * `:tag` carries a PHP type expression used by the emitter for return-type
     * declarations. The reader hands it in as a `Symbol` (`^int x`) or `String`
     * (`^"?int" x`); store it as a string in the runtime meta map so the
     * compiled `\Phel::map(:tag, ...)` literal does not resolve `int` against
     * the global environment as a var reference.
     */
    private function normalizeMetaValue(mixed $key, mixed $value): mixed
    {
        if ($key instanceof Keyword && $key->getName() === 'tag' && $value instanceof Symbol) {
            return $value->getName();
        }

        return $value;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentMapInterface<mixed, mixed>
     */
    private function normalizeMeta(mixed $meta, PersistentListInterface $list): PersistentMapInterface
    {
        if (is_string($meta)) {
            $key = (Keyword::create('doc'))->copyLocationFrom($list);

            return Phel::map($key, $meta)
                ->copyLocationFrom($list);
        }

        if ($meta instanceof Keyword) {
            return Phel::map($meta, true)
                ->copyLocationFrom($meta);
        }

        if ($meta instanceof PersistentMapInterface) {
            return $meta;
        }

        throw AnalyzerException::wrongArgumentType('Metadata', ['String', 'Keyword', 'Map'], $meta, $list);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return array{0: mixed, 1: mixed}
     */
    private function getInitialMetaAndInit(PersistentListInterface $list): array
    {
        if (count($list) === 2) {
            return [Phel::map(), null];
        }

        if (count($list) === 3) {
            return [Phel::map(), $list->get(2)];
        }

        return [$list->get(2), $list->get(3)];
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    private function analyzeInit(
        float|bool|int|string|TypeInterface|null $init,
        NodeEnvironmentInterface $env,
        string $namespace,
        Symbol $nameSymbol,
        PersistentMapInterface $meta,
    ): AbstractNode {
        $initEnv = $env
            ->withExpressionContext()
            ->withDisallowRecurFrame();

        // The self-call shortcut keys off boundTo. For memoised defs the
        // wrapper, not the inner fn, must receive recursive calls, so leave
        // boundTo unset to keep recursion routed through the registry.
        if (!$this->isMemoised($meta)) {
            $initEnv = $initEnv->withBoundTo($namespace . '\\' . $nameSymbol->__toString());
        }

        return $this->analyzer->analyze($init, $initEnv);
    }

    /**
     * Matches the Phel-truthy check `defn-builder` uses on the same keys, so
     * a literal `^{:memoize false}` does not flip the wrapper on.
     *
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    private function isMemoised(PersistentMapInterface $meta): bool
    {
        if ((bool) $meta[Keyword::create('memoize')]) {
            return true;
        }

        return (bool) $meta[Keyword::create('memoize-lru')];
    }

    private function buildFnNodeArglist(FnNode $fnNode, int $skipFirst = 0): string
    {
        return $this->formatParamsVector($fnNode->getParams(), $fnNode->isVariadic(), $skipFirst);
    }

    /**
     * Inferred return type writes back to the def's runtime meta as `:tag`
     * so cross-fn inference can see it on the next call site without
     * having to re-walk the callee's body. User `:tag` already in meta
     * stays untouched.
     *
     * @param PersistentMapInterface<mixed, mixed> $metaMap
     *
     * @return PersistentMapInterface<mixed, mixed>
     */
    private function persistInferredReturnTag(PersistentMapInterface $metaMap, ?string $returnType): PersistentMapInterface
    {
        if ($returnType === null || $returnType === '') {
            return $metaMap;
        }

        if ($metaMap->find(Keyword::create('tag')) !== null) {
            return $metaMap;
        }

        return $metaMap->put(Keyword::create('tag'), $returnType);
    }

    /**
     * Single source of truth for the inferred return type used to
     * stamp `:tag` on the def's runtime meta. Single-arity surfaces
     * its FnNode return type directly; multi-arity surfaces a return
     * type only when every arity agrees, mirroring how the inferrer
     * bails on `if` branches that disagree.
     */
    private function inferredReturnTypeOf(?AbstractNode $initNode): ?string
    {
        if ($initNode instanceof FnNode) {
            return $initNode->getReturnType();
        }

        if (!$initNode instanceof MultiFnNode) {
            return null;
        }

        $shared = null;
        foreach ($initNode->getFnNodes() as $fnNode) {
            $type = $fnNode->getReturnType();
            if ($type === null) {
                return null;
            }

            if ($shared === null) {
                $shared = $type;
                continue;
            }

            if ($shared !== $type) {
                return null;
            }
        }

        return $shared;
    }

    /**
     * Static-checker view of the params: a Phel vector with the same
     * arity, where each slot is the param's `:tag` (string) or `null`
     * if untagged. The variadic tail is excluded; its `:tag` describes
     * element type, not the bound `Vector`.
     *
     * @return PersistentVectorInterface<mixed>
     */
    private function buildParamTags(FnNode $fnNode, int $skipFirst = 0): PersistentVectorInterface
    {
        $params = $this->scalarParamSlice($fnNode, $skipFirst);
        $tags = array_map(
            TagCompatibility::extractParamTag(...),
            $params,
        );

        return Phel::vector($tags);
    }

    /**
     * Walks the fn body with `ParamTypeInferrer` and stamps each
     * unambiguously-typed param's `:tag` directly onto the Symbol's
     * metadata so the emitter renders `int $x` / `string $x` in the
     * compiled PHP signature. User tags win: a Symbol that already
     * carries `:tag` is left alone.
     *
     * Mutates the param Symbols held by `FnNode::$params` in place via
     * `Symbol::withMeta`. The variadic tail and any leading macro
     * implicit params are excluded. After grafting, re-runs return-type
     * inference so the body's tail expression sees the now-tagged
     * locals and the fn surfaces a return-type declaration when it
     * could not before.
     */
    private function graftInferredParamTags(FnNode $fnNode, int $skipFirst = 0): void
    {
        $params = $this->scalarParamSlice($fnNode, $skipFirst);
        if ($params === []) {
            return;
        }

        $inferred = new ParamTypeInferrer()->infer(
            $fnNode->getBody(),
            $params,
            $fnNode->isVariadic(),
        );

        if ($inferred === []) {
            return;
        }

        $grafted = false;
        foreach ($params as $param) {
            $tag = $inferred[$param->getName()] ?? null;
            if ($tag === null) {
                continue;
            }

            $existing = $param->getMeta();
            if ($existing instanceof PersistentMapInterface
                && $existing->find(Keyword::create('tag')) !== null
            ) {
                continue;
            }

            $merged = ($existing ?? Phel::map())
                ->put(Keyword::create('tag'), Symbol::create($tag));
            $param->withMeta($merged);
            $grafted = true;
        }

        if ($grafted && $fnNode->getReturnType() === null) {
            $fnNode->fillInferredReturnType(
                new ReturnTypeInferrer()->infer(
                    $fnNode->getBody(),
                    $fnNode->getParams(),
                    $fnNode->isVariadic(),
                ),
            );
        }
    }

    /**
     * Skips the leading macro implicit params (`&form`, `&env`) and the
     * variadic tail. Both `:param-tags` consumers operate on this slice
     * because the variadic tail's `:tag` describes the element type, not
     * the bound `Vector`.
     *
     * @return list<Symbol>
     */
    private function scalarParamSlice(FnNode $fnNode, int $skipFirst): array
    {
        $params = $fnNode->getParams();
        if ($skipFirst > 0) {
            $params = array_slice($params, $skipFirst);
        }

        if ($fnNode->isVariadic() && $params !== []) {
            array_pop($params);
        }

        return $params;
    }

    private function buildMultiFnNodeArglists(MultiFnNode $multiFnNode, int $skipFirst = 0): string
    {
        $vectors = [];
        foreach ($multiFnNode->getFnNodes() as $fnNode) {
            $vectors[] = $this->formatParamsVector($fnNode->getParams(), $fnNode->isVariadic(), $skipFirst);
        }

        return '(' . implode(' ', $vectors) . ')';
    }

    /**
     * @param list<Symbol> $params
     */
    private function formatParamsVector(array $params, bool $isVariadic, int $skipFirst = 0): string
    {
        if ($skipFirst > 0) {
            $params = array_slice($params, $skipFirst);
        }

        $names = array_map(
            static fn(Symbol $s): string => $s->getName(),
            $params,
        );

        if ($isVariadic && $names !== []) {
            $restParam = array_pop($names);
            $names[] = '&';
            $names[] = $restParam;
        }

        return '[' . implode(' ', $names) . ']';
    }

    /**
     * Rewrites a `(fn ...)` init expression to prepend `&form` and `&env` to each arity's
     * parameter vector. Supports both single-arity and multi-arity fn forms. Returns the
     * init unchanged if it is not a `(fn ...)` list or if the implicit params are already
     * present (idempotent / Clojure-style shadowing).
     */
    private function injectMacroImplicitParams(mixed $init): mixed
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
            return $this->rewriteSingleArityFn($init);
        }

        if ($second instanceof PersistentListInterface) {
            return $this->rewriteMultiArityFn($init);
        }

        return $init;
    }

    /**
     * @param PersistentListInterface<mixed> $fnList
     *
     * @return PersistentListInterface<mixed>
     */
    private function rewriteSingleArityFn(PersistentListInterface $fnList): PersistentListInterface
    {
        $items = $fnList->toArray();
        /** @var PersistentVectorInterface<mixed> $params */
        $params = $items[1];
        $items[1] = $this->prependImplicitParams($params);

        return Phel::list($items)->copyLocationFrom($fnList);
    }

    /**
     * @param PersistentListInterface<mixed> $fnList
     *
     * @return PersistentListInterface<mixed>
     */
    private function rewriteMultiArityFn(PersistentListInterface $fnList): PersistentListInterface
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

            $arityItems[0] = $this->prependImplicitParams($arityItems[0]);
            $items[$i] = Phel::list($arityItems)->copyLocationFrom($arity);
        }

        return Phel::list($items)->copyLocationFrom($fnList);
    }

    /**
     * When the def's metadata carries `:tag` (typically from the name symbol,
     * e.g. `(defn ^int add [x y] ...)`), splice that tag onto each fn arity's
     * param vector so `FnSymbol` can pick it up as the compiled signature's
     * return type. Skips arities that already declare their own vector `:tag`
     * (more local declaration wins).
     *
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    private function injectReturnTypeFromMeta(mixed $init, PersistentMapInterface $meta): mixed
    {
        $tag = $meta->find(Keyword::create('tag'));
        if (!($tag instanceof Symbol) && !is_string($tag)) {
            return $init;
        }

        if (!$init instanceof PersistentListInterface) {
            return $init;
        }

        $head = $init->first();
        if (!$head instanceof Symbol || $head->getName() !== Symbol::NAME_FN) {
            return $init;
        }

        $second = $init->get(1);
        if ($second instanceof PersistentVectorInterface) {
            return $this->rewriteSingleArityWithTag($init, $tag);
        }

        if ($second instanceof PersistentListInterface) {
            return $this->rewriteMultiArityWithTag($init, $tag);
        }

        return $init;
    }

    /**
     * @param PersistentListInterface<mixed> $fnList
     *
     * @return PersistentListInterface<mixed>
     */
    private function rewriteSingleArityWithTag(PersistentListInterface $fnList, mixed $tag): PersistentListInterface
    {
        $items = $fnList->toArray();
        /** @var PersistentVectorInterface<mixed> $params */
        $params = $items[1];
        $items[1] = $this->withReturnTypeTag($params, $tag);

        return Phel::list($items)->copyLocationFrom($fnList);
    }

    /**
     * @param PersistentListInterface<mixed> $fnList
     *
     * @return PersistentListInterface<mixed>
     */
    private function rewriteMultiArityWithTag(PersistentListInterface $fnList, mixed $tag): PersistentListInterface
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

            $arityItems[0] = $this->withReturnTypeTag($arityItems[0], $tag);
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
