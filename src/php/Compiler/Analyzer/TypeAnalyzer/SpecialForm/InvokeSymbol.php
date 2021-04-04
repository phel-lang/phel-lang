<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Exception;
use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\CallNode;
use Phel\Compiler\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;

final class InvokeSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        $f = $this->analyzer->analyze(
            $list->get(0),
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
        );

        if ($f instanceof GlobalVarNode && $f->isMacro()) {
            return $this->globalMacro($list, $f, $env);
        }

        return new CallNode(
            $env,
            $f,
            $this->arguments($list, $env),
            $list->getStartLocation()
        );
    }

    private function globalMacro(PersistentListInterface $list, GlobalVarNode $f, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->analyzer->analyzeMacro($this->macroExpand($list, $f), $env);
    }

    /**
     * @return TypeInterface|string|float|int|bool|null
     */
    private function macroExpand(PersistentListInterface $list, GlobalVarNode $macroNode)
    {
        $listCount = count($list);
        /** @psalm-suppress PossiblyNullArgument */
        $nodeName = $macroNode->getName()->getName();
        $fn = $GLOBALS['__phel'][$macroNode->getNamespace()][$nodeName];

        $arguments = [];
        for ($i = 1; $i < $listCount; $i++) {
            $arguments[] = $list->get($i);
        }

        try {
            $result = $fn(...$arguments);
            return $this->enrichLocation($result, $list);
        } catch (Exception $e) {
            throw AnalyzerException::withLocation(
                'Error in expanding macro "' . $macroNode->getNamespace() . '\\' . $nodeName . '": ' . $e->getMessage(),
                $list,
                $e
            );
        }
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $x
     *
     * @return TypeInterface|string|float|int|bool|null
     */
    private function enrichLocation($x, TypeInterface $parent)
    {
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
            $parent
        );
    }

    private function enrichLocationForAbstractType(TypeInterface $type, TypeInterface $parent): TypeInterface
    {
        if (!$type->getStartLocation()) {
            $type = $type->setStartLocation($parent->getStartLocation());
        }

        if (!$type->getEndLocation()) {
            $type = $type->setEndLocation($parent->getEndLocation());
        }

        return $type;
    }

    private function arguments(PersistentListInterface $list, NodeEnvironmentInterface $env): array
    {
        $arguments = [];
        for ($i = 1, $iMax = count($list); $i < $iMax; $i++) {
            $arguments[] = $this->analyzer->analyze(
                $list->get($i),
                $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
            );
        }

        return $arguments;
    }
}
