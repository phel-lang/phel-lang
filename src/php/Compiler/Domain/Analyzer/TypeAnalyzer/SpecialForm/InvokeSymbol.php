<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Exception;
use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\TypeInterface;
use Phel\Printer\Printer;
use RuntimeException;

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
 */
final class InvokeSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    private const string UNHANDLED = "\0__phel_unhandled__";

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

        return new CallNode(
            $env,
            $f,
            $this->arguments($list->rest(), $env),
            $list->getStartLocation(),
        );
    }

    /**
     * Guards against call-position literals that PHP would reject with a raw
     * `TypeError` at runtime (numbers, strings, booleans, `nil`). Keywords,
     * symbols and persistent maps/sets/vectors stay callable and are handled
     * at runtime.
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

        if (!$meta[Keyword::create('inline')]) {
            return false;
        }

        $arityFn = $meta[Keyword::create('inline-arity')];

        if (!$arityFn) {
            return true;
        }

        return $arityFn($arity);
    }

    private function globalMacro(
        PersistentListInterface $list,
        GlobalVarNode $f,
        NodeEnvironmentInterface $env,
    ): AbstractNode {
        return $this->analyzer->analyzeMacro($this->macroExpand($list, $f, $env), $env);
    }

    private function inlineExpand(
        PersistentListInterface $list,
        GlobalVarNode $node,
    ): float|bool|int|string|TypeInterface|array|null {
        $meta = $node->getMeta();
        $fn = $meta[Keyword::create('inline')];

        if (!is_callable($fn)) {
            throw AnalyzerException::whenExpandingInlineFn($list, $node, new RuntimeException('Inline metadata is not callable.'));
        }

        try {
            return $this->callInlineFn($fn, $list);
        } catch (Exception $exception) {
            throw AnalyzerException::whenExpandingInlineFn($list, $node, $exception);
        }
    }

    private function macroExpand(
        PersistentListInterface $list,
        GlobalVarNode $macroNode,
        NodeEnvironmentInterface $env,
    ): float|bool|int|string|TypeInterface|array|null {
        /** @psalm-suppress PossiblyNullArgument */
        $nodeName = $macroNode->getName()->getName();

        $ns = str_replace('-', '_', $macroNode->getNamespace());
        $fn = Phel::getDefinition($ns, $nodeName);

        if (!is_callable($fn)) {
            throw AnalyzerException::whenExpandingMacro($list, $macroNode, new RuntimeException(sprintf('Macro "%s::%s" is not callable.', $ns, $nodeName)));
        }

        try {
            return $this->callMacroFn($fn, $list, $env);
        } catch (Exception $exception) {
            throw AnalyzerException::whenExpandingMacro($list, $macroNode, $exception);
        }
    }

    private function callMacroFn(
        callable $fn,
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
    ): float|bool|int|string|TypeInterface|array|null {
        $envMap = $this->buildEnvMap($env);
        $arguments = $list->rest()->toArray();

        $result = $fn($list, $envMap, ...$arguments);
        return $this->enrichLocation($result, $list);
    }

    private function callInlineFn(
        callable $fn,
        PersistentListInterface $list,
    ): float|bool|int|string|TypeInterface|array|null {
        $arguments = $list->rest()->toArray();

        $result = $fn(...$arguments);
        return $this->enrichLocation($result, $list);
    }

    /**
     * Builds the `&env` map passed to macro functions. Keys are symbols of the
     * locals in scope at the macro call site; values mirror the keys. This
     * mirrors Clojure's `&env` shape enough to support patterns like
     * `(contains? &env 'x)`, `(keys &env)`, and `(:ns &env)`.
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

    private function enrichLocation(
        float|bool|int|string|TypeInterface|array|null $x,
        TypeInterface $parent,
    ): float|bool|int|string|TypeInterface|array|null {
        if ($x instanceof PersistentListInterface) {
            return $this->enrichLocationForList($x, $parent);
        }

        if ($x instanceof TypeInterface) {
            return $this->enrichLocationForAbstractType($x, $parent);
        }

        return $x;
    }

    private function enrichLocationForList(PersistentListInterface $list, TypeInterface $parent): TypeInterface
    {
        $result = [];
        foreach ($list->getIterator() as $item) {
            $result[] = $this->enrichLocation($item, $parent);
        }

        return $this->enrichLocationForAbstractType(
            Phel::list($result)->withMeta($list->getMeta()),
            $parent,
        );
    }

    private function enrichLocationForAbstractType(TypeInterface $type, TypeInterface $parent): TypeInterface
    {
        if (!$type->getStartLocation() instanceof SourceLocation) {
            $type = $type->setStartLocation($parent->getStartLocation());
        }

        if (!$type->getEndLocation() instanceof SourceLocation) {
            return $type->setEndLocation($parent->getEndLocation());
        }

        return $type;
    }

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

    private function validateEnoughArgsProvided(GlobalVarNode $f, PersistentListInterface $list): void
    {
        $nodeName = $f->getName()->getName();
        $data = Phel::getDefinitionMetaData($f->getNamespace(), $nodeName);

        if (!$data instanceof PersistentMapInterface) {
            return;
        }

        $minArity = $data->find('min-arity');

        if ($minArity === null) {
            return;
        }

        $gotCount = count($list->rest());
        $isVariadic = (bool) $data->find('is-variadic');
        $maxArity = $data->find('max-arity');

        if ($gotCount < $minArity) {
            throw AnalyzerException::notEnoughArgsProvided($f, $list, $minArity, $isVariadic, $maxArity);
        }

        if (!$isVariadic && $maxArity !== null && $gotCount > $maxArity) {
            throw AnalyzerException::tooManyArgsProvided($f, $list, $minArity, $maxArity);
        }
    }
}
