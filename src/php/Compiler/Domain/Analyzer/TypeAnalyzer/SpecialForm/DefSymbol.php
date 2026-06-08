<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
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
use function is_scalar;
use function is_string;
use function max;

/**
 * (def name value).
 *
 * Defines a global variable in the current namespace.
 */
final readonly class DefSymbol implements SpecialFormAnalyzerInterface
{
    /** @var array<int, true> */
    private const array POSSIBLE_TUPLE_SIZES = [2 => true, 3 => true, 4 => true];

    /** Number of implicit parameters (`&form` and `&env`) injected into macro fns. */
    private const int MACRO_IMPLICIT_PARAMS = 2;

    /**
     * `defonce`: `false` for the plain `def` special form (always
     * overwrite the existing binding), `true` for `defonce*` (only
     * initialise when the binding is absent — used by REPL workflows to
     * keep stateful objects alive across file reloads).
     */
    public function __construct(
        private AnalyzerInterface $analyzer,
        private bool $defonce = false,
    ) {}

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

        $this->analyzer->addDefinition($namespace, $nameSymbol, $this->defonce);

        [$metaMap, $init] = $this->createMetaMapAndInit($list);

        $rewriter = new MacroFormRewriter();
        $isMacro = $metaMap[Keyword::create('macro')] === true;
        if ($isMacro) {
            $init = $rewriter->injectImplicitParams($init);
        }

        $init = $rewriter->injectReturnTypeFromMeta($init, $metaMap);

        $initNode = $this->analyzeInit($init, $env, $namespace, $nameSymbol, $metaMap);
        $skip = $isMacro ? self::MACRO_IMPLICIT_PARAMS : 0;

        if ($initNode instanceof FnNode) {
            $initNode->markAsDefinition();
            $this->analyzer->setDefFnNode($namespace, $nameSymbol, $initNode);
            // Macro args bind to raw Phel forms regardless of how the
            // body manipulates them, so a primitive-op observation in
            // the macro body must not type-narrow the runtime signature.
            if (!$isMacro) {
                $this->graftInferredParamTags($initNode, $skip, $namespace, $nameSymbol->getName());
            }

            $metaMap = $metaMap->put('min-arity', max(0, $initNode->getMinArity() - $skip));
            $metaMap = $metaMap->put('is-variadic', $initNode->isVariadic());
            $metaMap = $metaMap->put('arglists', new DefArglistBuilder()->buildFnNodeArglist($initNode, $skip));
        } elseif ($initNode instanceof MultiFnNode) {
            // Stash multi-arity defs too so the inliner can select the
            // arity matching a call's argument count (#2218).
            $this->analyzer->setDefFnNode($namespace, $nameSymbol, $initNode);
            $metaMap = $metaMap->put('min-arity', max(0, $initNode->getMinArity() - $skip));
            $metaMap = $metaMap->put('is-variadic', $initNode->isVariadic());
            $maxArity = $initNode->getMaxArity();
            $metaMap = $metaMap->put('max-arity', $maxArity === null ? null : max(0, $maxArity - $skip));
            $metaMap = $metaMap->put('arglists', new DefArglistBuilder()->buildMultiFnNodeArglists($initNode, $skip));
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
            $this->defonce,
        );
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function verifySizeOfTuple(PersistentListInterface $list): void
    {
        $listSize = count($list);

        if (!isset(self::POSSIBLE_TUPLE_SIZES[$listSize])) {
            throw AnalyzerException::withLocation(
                "Two or three arguments are required for 'def. Got " . $listSize,
                $list,
            );
        }
    }

    /**
     * Separates the def's compile-time metadata from its init expression.
     *
     * Returns `[$metaMap, $init]`. The meta map normalizes the explicit
     * metadata form (string `:doc`, keyword flag, or map), then folds in
     * flags attached to the name symbol (e.g. `^:dynamic`, `^:private`),
     * any list-level meta, and the def's start/end source locations so
     * runtime code and the emitter can read them off the var.
     *
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
    private function graftInferredParamTags(
        FnNode $fnNode,
        int $skipFirst = 0,
        ?string $selfNamespace = null,
        ?string $selfName = null,
    ): void {
        $params = $this->scalarParamSlice($fnNode, $skipFirst);
        if ($params === []) {
            return;
        }

        $inferred = new ParamTypeInferrer()->infer(
            $fnNode->getBody(),
            $params,
            $fnNode->isVariadic(),
            $selfNamespace,
            $selfName,
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
                    $selfNamespace,
                    $selfName,
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
}
