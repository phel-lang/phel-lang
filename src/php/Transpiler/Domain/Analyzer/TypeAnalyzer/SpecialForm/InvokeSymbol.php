<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Exception;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\SourceLocation;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Ast\CallNode;
use Phel\Transpiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

use function count;

final class InvokeSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

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

        return new CallNode(
            $env,
            $f,
            $this->arguments($list->rest(), $env),
            $list->getStartLocation(),
        );
    }

    private function inlineMacro(PersistentListInterface $list, GlobalVarNode $f, NodeEnvironmentInterface $env): AbstractNode
    {
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

    private function globalMacro(PersistentListInterface $list, GlobalVarNode $f, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->analyzer->analyzeMacro($this->macroExpand($list, $f), $env);
    }

    private function inlineExpand(PersistentListInterface $list, GlobalVarNode $node): float|bool|int|string|TypeInterface|array|null
    {
        $meta = $node->getMeta();
        $fn = $meta[Keyword::create('inline')];

        try {
            return $this->callMacroFn($fn, $list);
        } catch (Exception $exception) {
            throw AnalyzerException::withLocation(
                'Error in expanding inline function of "' . $node->getNamespace() . '\\' . $node->getName()->getName() . '": ' . $exception->getMessage(),
                $list,
                $exception,
            );
        }
    }

    private function macroExpand(PersistentListInterface $list, GlobalVarNode $macroNode): float|bool|int|string|TypeInterface|array|null
    {
        /** @psalm-suppress PossiblyNullArgument */
        $nodeName = $macroNode->getName()->getName();
        $fn = Registry::getInstance()->getDefinition($macroNode->getNamespace(), $nodeName);

        try {
            return $this->callMacroFn($fn, $list);
        } catch (Exception $exception) {
            throw AnalyzerException::withLocation(
                'Error in expanding macro "' . $macroNode->getNamespace() . '\\' . $nodeName . '": ' . $exception->getMessage(),
                $list,
                $exception,
            );
        }
    }

    private function callMacroFn(callable $fn, PersistentListInterface $list): float|bool|int|string|TypeInterface|array|null
    {
        $arguments = $list->rest()->toArray();

        $result = $fn(...$arguments);
        return $this->enrichLocation($result, $list);
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
            TypeFactory::getInstance()->persistentListFromArray($result)->withMeta($list->getMeta()),
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
}
