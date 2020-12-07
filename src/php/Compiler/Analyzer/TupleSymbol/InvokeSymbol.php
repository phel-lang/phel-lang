<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Exception;
use Phel\Compiler\Analyzer\WithAnalyzer;
use Phel\Compiler\Ast\CallNode;
use Phel\Compiler\Ast\GlobalVarNode;
use Phel\Compiler\Ast\Node;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\Tuple;

final class InvokeSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): Node
    {
        $f = $this->analyzer->analyze(
            $tuple[0],
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
        );

        if ($f instanceof GlobalVarNode && $f->isMacro()) {
            return $this->globalMacro($tuple, $env);
        }

        return new CallNode(
            $env,
            $f,
            $this->arguments($tuple, $env),
            $tuple->getStartLocation()
        );
    }

    private function globalMacro(Tuple $tuple, NodeEnvironmentInterface $env): Node
    {
        return $this->analyzer->analyzeMacro($this->macroExpand($tuple, $env), $env);
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function macroExpand(Tuple $tuple, NodeEnvironmentInterface $env)
    {
        $tupleCount = count($tuple);
        /** @psalm-suppress PossiblyNullArgument */
        $node = $this->analyzer->resolve($tuple[0], $env);
        if ($node && $node instanceof GlobalVarNode) {
            $nodeName = $node->getName()->getName();
            $fn = $GLOBALS['__phel'][$node->getNamespace()][$nodeName];

            $arguments = [];
            for ($i = 1; $i < $tupleCount; $i++) {
                $arguments[] = $tuple[$i];
            }

            try {
                $result = $fn(...$arguments);
                $this->enrichLocation($result, $tuple);

                return $result;
            } catch (Exception $e) {
                throw AnalyzerException::withLocation(
                    'Error in expanding macro "' . $node->getNamespace() . '\\' . $nodeName . '": ' . $e->getMessage(),
                    $tuple,
                    $e
                );
            }
        }

        if (is_null($node)) {
            throw AnalyzerException::withLocation('Can not resolve macro', $tuple);
        }

        throw AnalyzerException::withLocation('This is not macro expandable: ' . get_class($node), $tuple);
    }

    /**
     * @param AbstractType|string|float|int|bool|null $x
     */
    private function enrichLocation($x, AbstractType $parent): void
    {
        if ($x instanceof Tuple) {
            $this->enrichLocationForTuple($x, $parent);
        } elseif ($x instanceof AbstractType) {
            $this->enrichLocationForAbstractType($x, $parent);
        }
    }

    private function enrichLocationForTuple(Tuple $tuple, AbstractType $parent): void
    {
        foreach ($tuple as $item) {
            $this->enrichLocation($item, $parent);
        }

        $this->enrichLocationForAbstractType($tuple, $parent);
    }

    private function enrichLocationForAbstractType(AbstractType $type, AbstractType $parent): void
    {
        if (!$type->getStartLocation()) {
            $type->setStartLocation($parent->getStartLocation());
        }

        if (!$type->getEndLocation()) {
            $type->setEndLocation($parent->getEndLocation());
        }
    }

    private function arguments(Tuple $tuple, NodeEnvironmentInterface $env): array
    {
        $arguments = [];
        for ($i = 1, $iMax = count($tuple); $i < $iMax; $i++) {
            $arguments[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
            );
        }

        return $arguments;
    }
}
