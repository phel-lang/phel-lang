<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Closure;
use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\ConstantFolder;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\CallInliner;
use Phel\Lang\AbstractFn;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\TypeInterface;
use Phel\Shared\Printer\Printer;
use RuntimeException;
use Throwable;

use function count;
use function get_debug_type;
use function is_bool;
use function is_callable;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * (f args...).
 *
 * Invokes a function or callable with the given arguments.
 *
 * @internal
 */
final readonly class InvokeSymbol implements SpecialFormAnalyzerInterface
{
    private const string UNHANDLED = "\0__phel_unhandled__";

    private CallInliner $callInliner;

    public function __construct(
        private AnalyzerInterface $analyzer,
        ?CallInliner $callInliner = null,
        private ConstantFolder $constantFolder = new ConstantFolder(),
    ) {
        $this->callInliner = $callInliner ?? new CallInliner();
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        $f = $this->analyzer->analyze(
            $list->first(),
            $env->withExpressionContext()->withDisallowRecurFrame(),
        );

        if ($f instanceof GlobalVarNode && $this->isInline($f, count($list) - 1)) {
            return $this->inlineMacro($list, $f, $env);
        }

        if ($f instanceof GlobalVarNode && $f->isMacro()) {
            return $this->globalMacro($list, $f, $env);
        }

        if ($f instanceof GlobalVarNode) {
            $this->validateEnoughArgsProvided($f, $list);
        }

        $this->rejectNonCallableLiteral($f, $list);

        /** @var PersistentListInterface<mixed> $rest */
        $rest = $list->rest();
        $args = $this->arguments($rest, $env);

        if ($f instanceof GlobalVarNode) {
            $this->verifyArgsAgainstParamTags($f, $args, $list);

            // Skip the inliner call on the default path; it only does
            // work at optimization level >= 2.
            if ($this->analyzer->getOptimizationLevel() >= 2) {
                $inlined = $this->callInliner->tryInline($f, $args, $env, $this->analyzer, $list->getStartLocation());
                if ($inlined instanceof AbstractNode) {
                    return $inlined;
                }
            }
        }

        $call = new CallNode(
            $env,
            $f,
            $args,
            $list->getStartLocation(),
        );

        return $this->constantFolder->fold($call) ?? $call;
    }

    /**
     * @param list<AbstractNode>             $args
     * @param PersistentListInterface<mixed> $list
     */
    private function verifyArgsAgainstParamTags(
        GlobalVarNode $f,
        array $args,
        PersistentListInterface $list,
    ): void {
        $meta = $f->getMeta();
        $paramTags = $meta->find(Keyword::create('param-tags'));
        if (!$paramTags instanceof PersistentVectorInterface) {
            return;
        }

        $tagsCount = count($paramTags);
        foreach ($args as $i => $arg) {
            if ($i >= $tagsCount) {
                return;
            }

            $tag = $this->tagAt($paramTags, $i);
            if ($tag !== null) {
                $this->ensureLiteralMatchesTag($f, $list, $i, $arg, $tag);
            }
        }
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function ensureLiteralMatchesTag(
        GlobalVarNode $f,
        PersistentListInterface $list,
        int $i,
        AbstractNode $arg,
        string $tag,
    ): void {
        $literalType = TagCompatibility::literalTypeOf($arg);
        if ($literalType === null || TagCompatibility::accepts($tag, $literalType)) {
            return;
        }

        throw AnalyzerException::withLocation(
            'Arg #' . ($i + 1) . " to '" . $f->getName()->getName()
            . sprintf("' has type '%s' but param is tagged '%s'", $literalType, $tag),
            $list,
        );
    }

    /**
     * @param PersistentVectorInterface<mixed> $vec
     */
    private function tagAt(PersistentVectorInterface $vec, int $i): ?string
    {
        if ($i >= count($vec)) {
            return null;
        }

        $tag = $vec->get($i);
        return is_string($tag) && $tag !== '' ? $tag : null;
    }

    /**
     * Guards against call-position literals that PHP would reject with a raw
     * `TypeError` at runtime (numbers, strings, booleans, `nil`). Keywords,
     * symbols and persistent maps/sets/vectors stay callable and are handled
     * at runtime.
     */
    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function rejectNonCallableLiteral(AbstractNode $f, PersistentListInterface $list): void
    {
        $value = match (true) {
            $f instanceof LiteralNode, $f instanceof QuoteNode => $f->getValue(),
            default => self::UNHANDLED,
        };

        if ($value === self::UNHANDLED) {
            return;
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            throw AnalyzerException::notCallable(
                Printer::readable()->print($value),
                get_debug_type($value),
                $list,
            );
        }
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function inlineMacro(
        PersistentListInterface $list,
        GlobalVarNode $f,
        NodeEnvironmentInterface $env,
    ): AbstractNode {
        return $this->analyzer->analyzeMacro($this->inlineExpand($list, $f), $env);
    }

    private function isInline(GlobalVarNode $node, int $arity): bool
    {
        $meta = $node->getMeta();

        if (!$this->isExpandableFn($meta[Keyword::create('inline')])) {
            return false;
        }

        $arityFn = $meta[Keyword::create('inline-arity')];

        if (!$this->isExpandableFn($arityFn)) {
            return true;
        }

        return (bool) $arityFn($arity);
    }

    /**
     * Whether a value read from `:inline` / `:inline-arity` metadata is a
     * function this analyzer may actually call.
     *
     * `is_callable()` is not that question. Phel's data types are invokable by
     * design: a list, vector, map, set, keyword and symbol all implement
     * `__invoke`, so every one of them satisfies `is_callable`. Metadata that
     * has not been evaluated is still the reader's `PersistentList` for the
     * `(fn ...)` form, and calling it lands on `PersistentList::__invoke($index)`
     * instead (#3055). For `:inline-arity` that is worse than a crash: the
     * arity is an int, so the list would accept it and return an element.
     *
     * Compiling a namespace evaluates the metadata first, so there the value is
     * a real `AbstractFn`. Analyzing without evaluating, as `phel lint` does,
     * leaves it as data, and the call site is expected to fall back to treating
     * the form as an ordinary function call.
     *
     * @psalm-assert-if-true callable $fn
     */
    private function isExpandableFn(mixed $fn): bool
    {
        return $fn instanceof AbstractFn || $fn instanceof Closure;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function globalMacro(
        PersistentListInterface $list,
        GlobalVarNode $f,
        NodeEnvironmentInterface $env,
    ): AbstractNode {
        return $this->analyzer->analyzeMacro($this->macroExpand($list, $f, $env), $env);
    }

    /**
     * `mixed` return: an inline expansion may produce literals outside the
     * Phel type system; see {@see self::callMacroFn()} / #2778. The result
     * feeds `analyzeMacro(mixed ...)`.
     *
     * @param PersistentListInterface<mixed> $list
     */
    private function inlineExpand(
        PersistentListInterface $list,
        GlobalVarNode $node,
    ): mixed {
        $meta = $node->getMeta();
        $fn = $meta[Keyword::create('inline')];

        if (!$this->isExpandableFn($fn)) {
            throw AnalyzerException::whenExpandingInlineFn($list, $node, new RuntimeException('Inline metadata is not callable.'));
        }

        try {
            return $this->callInlineFn($fn, $list, $this->definitionLocation($node));
        } catch (Throwable $throwable) {
            throw AnalyzerException::whenExpandingInlineFn($list, $node, $throwable);
        }
    }

    /**
     * Where the macro / inline definition itself was written, read from the
     * `:start-location` its `def` records. Falls back to
     * {@see SourceLocation::unknown()} so an expansion is always
     * distinguishable from code the user typed at the call site, even when
     * the definition carries no location.
     */
    private function definitionLocation(GlobalVarNode $node): SourceLocation
    {
        $location = $node->getMeta()->find(Keyword::create('start-location'));
        if (!$location instanceof PersistentMapInterface) {
            return SourceLocation::unknown();
        }

        $file = $location->find(Keyword::create('file'));
        $line = $location->find(Keyword::create('line'));
        $column = $location->find(Keyword::create('column'));

        if (!is_string($file) || !is_int($line) || !is_int($column)) {
            return SourceLocation::unknown();
        }

        return new SourceLocation($file, $line, $column);
    }

    /**
     * `mixed` return: a macro expansion may produce literals outside the
     * Phel type system; see {@see self::callMacroFn()} / #2778. The result
     * feeds `analyzeMacro(mixed ...)`.
     *
     * @param PersistentListInterface<mixed> $list
     */
    private function macroExpand(
        PersistentListInterface $list,
        GlobalVarNode $macroNode,
        NodeEnvironmentInterface $env,
    ): mixed {
        $nodeName = $macroNode->getName()->getName();

        $ns = str_replace('-', '_', $macroNode->getNamespace());
        $fn = Phel::getDefinition($ns, $nodeName);

        if (!is_callable($fn)) {
            throw AnalyzerException::whenExpandingMacro($list, $macroNode, new RuntimeException(sprintf('Macro "%s::%s" is not callable.', $ns, $nodeName)));
        }

        try {
            return $this->callMacroFn($fn, $list, $env, $this->definitionLocation($macroNode));
        } catch (Throwable $throwable) {
            throw AnalyzerException::whenExpandingMacro($list, $macroNode, $throwable);
        }
    }

    /**
     * `mixed` return: a macro may expand to literals outside the Phel type
     * system (e.g. `#inst` → `DateTimeImmutable`); see enrichLocation/#2778.
     *
     * @param PersistentListInterface<mixed> $list
     */
    private function callMacroFn(
        callable $fn,
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
        SourceLocation $origin,
    ): mixed {
        $envMap = $this->buildEnvMap($env);
        /** @var PersistentListInterface<mixed> $rest */
        $rest = $list->rest();
        $arguments = $rest->toArray();

        $result = $fn($list, $envMap, ...$arguments);
        return $this->enrichLocation($result, $list, $origin);
    }

    /**
     * `mixed` return for the same reason as {@see self::callMacroFn()}.
     *
     * @param PersistentListInterface<mixed> $list
     */
    private function callInlineFn(
        callable $fn,
        PersistentListInterface $list,
        SourceLocation $origin,
    ): mixed {
        /** @var PersistentListInterface<mixed> $rest */
        $rest = $list->rest();
        $arguments = $rest->toArray();

        $result = $fn(...$arguments);
        return $this->enrichLocation($result, $list, $origin);
    }

    /**
     * Builds the `&env` map passed to macro functions. Keys are symbols of the
     * locals in scope at the macro call site; values mirror the keys. This
     * mirrors Clojure's `&env` shape enough to support patterns like
     * `(contains? &env 'x)`, `(keys &env)`, and `(:ns &env)`.
     */
    /**
     * @return PersistentMapInterface<mixed, mixed>
     */
    private function buildEnvMap(NodeEnvironmentInterface $env): PersistentMapInterface
    {
        $kvs = [];
        foreach ($env->getLocals() as $local) {
            $kvs[] = $local;
            $kvs[] = $local;
        }

        return Phel::map(...$kvs);
    }

    /**
     * `mixed` deliberately: a macro expansion may contain literals outside
     * the Phel type system (e.g. `#inst` yields a raw `DateTimeImmutable`);
     * anything that is neither a list nor a `TypeInterface` passes through
     * untouched — see #2778.
     */
    private function enrichLocation(
        mixed $x,
        TypeInterface $parent,
        SourceLocation $origin,
    ): mixed {
        if ($x instanceof PersistentListInterface) {
            return $this->enrichLocationForList($x, $parent, $origin);
        }

        if ($x instanceof TypeInterface) {
            return $this->enrichLocationForAbstractType($x, $parent, $origin);
        }

        return $x;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function enrichLocationForList(
        PersistentListInterface $list,
        TypeInterface $parent,
        SourceLocation $origin,
    ): TypeInterface {
        $result = [];
        $changed = false;
        foreach ($list->getIterator() as $item) {
            $enriched = $this->enrichLocation($item, $parent, $origin);
            if ($enriched !== $item) {
                $changed = true;
            }

            $result[] = $enriched;
        }

        // A list whose children all came back identical, and which already
        // carries both of its own locations, rebuilds into a copy of itself:
        // same elements, same meta, same positions, and the stamping below
        // then finds nothing to stamp. Most of a macro expansion is that
        // case, and each rebuild allocates a list and a meta wrapper for it.
        if (!$changed
            && $list->getStartLocation() instanceof SourceLocation
            && $list->getEndLocation() instanceof SourceLocation
        ) {
            return $list;
        }

        // The rebuilt list must keep the position of the one it replaces:
        // locations are not metadata, so `withMeta` alone would hand back a
        // list with none and the stamping below would then put the call site
        // on a form the user wrote (every `is` in a `deftest` reported the
        // `deftest` line, #3228).
        $rebuilt = Phel::list($result)
            ->withMeta($list->getMeta())
            ->setStartLocation($list->getStartLocation())
            ->setEndLocation($list->getEndLocation());

        return $this->enrichLocationForAbstractType($rebuilt, $parent, $origin);
    }

    /**
     * Stamps the call site onto forms the expansion produced, so errors keep
     * pointing at the code the user wrote. Only forms that carry no location
     * of their own are stamped, and those are exactly the ones the expansion
     * synthesised — macro arguments arrive with the reader's own positions and
     * are left alone. The stamped location records `$origin` so consumers can
     * still tell where the form was really authored (#2827).
     */
    private function enrichLocationForAbstractType(
        TypeInterface $type,
        TypeInterface $parent,
        SourceLocation $origin,
    ): TypeInterface {
        if (!$type->getStartLocation() instanceof SourceLocation) {
            $type = $type->setStartLocation(
                $parent->getStartLocation()?->withExpansionOrigin($origin),
            );
        }

        if (!$type->getEndLocation() instanceof SourceLocation) {
            return $type->setEndLocation(
                $parent->getEndLocation()?->withExpansionOrigin($origin),
            );
        }

        return $type;
    }

    /**
     * @param PersistentListInterface<mixed> $argsList
     *
     * @return list<AbstractNode>
     */
    private function arguments(PersistentListInterface $argsList, NodeEnvironmentInterface $env): array
    {
        $arguments = [];
        foreach ($argsList as $argList) {
            $arguments[] = $this->analyzer->analyze(
                $argList,
                $env->withExpressionContext()->withDisallowRecurFrame(),
            );
        }

        return $arguments;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function validateEnoughArgsProvided(GlobalVarNode $f, PersistentListInterface $list): void
    {
        $nodeName = $f->getName()->getName();
        $data = Phel::getDefinitionMetaData($f->getNamespace(), $nodeName);

        if (!$data instanceof PersistentMapInterface) {
            return;
        }

        $minArity = $data->find('min-arity');

        if (!is_int($minArity)) {
            return;
        }

        /** @var PersistentListInterface<mixed> $rest */
        $rest = $list->rest();
        $gotCount = count($rest);
        $isVariadic = (bool) $data->find('is-variadic');
        $maxArityValue = $data->find('max-arity');
        $maxArity = is_int($maxArityValue) ? $maxArityValue : null;

        if ($gotCount < $minArity) {
            throw AnalyzerException::notEnoughArgsProvided($f, $list, $minArity, $isVariadic, $maxArity);
        }

        if (!$isVariadic && $maxArity !== null && $gotCount > $maxArity) {
            throw AnalyzerException::tooManyArgsProvided($f, $list, $minArity, $maxArity);
        }
    }
}
