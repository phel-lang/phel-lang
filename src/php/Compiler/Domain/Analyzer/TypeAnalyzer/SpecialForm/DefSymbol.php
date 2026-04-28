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
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

use function array_map;
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

        $initNode = $this->analyzeInit($init, $env, $namespace, $nameSymbol);
        if ($initNode instanceof FnNode) {
            $initNode->markAsDefinition();
        }

        $skip = $isMacro ? self::MACRO_IMPLICIT_PARAMS : 0;
        if ($initNode instanceof FnNode) {
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
     * @return array{0:PersistentMapInterface, 1:mixed}
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
                    $meta = $meta->put($key, $value);
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

    private function analyzeInit(
        float|bool|int|string|TypeInterface|null $init,
        NodeEnvironmentInterface $env,
        string $namespace,
        Symbol $nameSymbol,
    ): AbstractNode {
        $initEnv = $env
            ->withBoundTo($namespace . '\\' . $nameSymbol->__toString())
            ->withExpressionContext()
            ->withDisallowRecurFrame();

        return $this->analyzer->analyze($init, $initEnv);
    }

    private function buildFnNodeArglist(FnNode $fnNode, int $skipFirst = 0): string
    {
        return $this->formatParamsVector($fnNode->getParams(), $fnNode->isVariadic(), $skipFirst);
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

    private function rewriteSingleArityFn(PersistentListInterface $fnList): PersistentListInterface
    {
        $items = $fnList->toArray();
        /** @var PersistentVectorInterface $params */
        $params = $items[1];
        $items[1] = $this->prependImplicitParams($params);

        return Phel::list($items)->copyLocationFrom($fnList);
    }

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
