<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\WithAnalyzerTrait;
use Phel\Compiler\Ast\RecurNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class RecurSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): RecurNode
    {
        if (!$this->isValidRecurTuple($tuple)) {
            throw AnalyzerException::withLocation("This is not a 'recur.", $tuple);
        }

        $currentFrame = $env->getCurrentRecurFrame();

        if (!$currentFrame) {
            /** @psalm-suppress PossiblyNullArgument */
            throw AnalyzerException::withLocation("Can't call 'recur here", $tuple[0]);
        }

        if (count($tuple) - 1 !== count($currentFrame->getParams())) {
            throw AnalyzerException::withLocation(
                "Wrong number of arguments for 'recur. Expected: "
                . count($currentFrame->getParams()) . ' args, got: ' . (count($tuple) - 1),
                $tuple
            );
        }

        $currentFrame->setIsActive(true);

        return new RecurNode(
            $env,
            $currentFrame,
            $this->expressions($tuple, $env),
            $tuple->getStartLocation()
        );
    }

    private function isValidRecurTuple(Tuple $tuple): bool
    {
        return $tuple[0] instanceof Symbol
            && $tuple[0]->getName() === Symbol::NAME_RECUR;
    }

    public function expressions(Tuple $tuple, NodeEnvironmentInterface $env): array
    {
        $expressions = [];

        for ($i = 1, $tupleCount = count($tuple); $i < $tupleCount; $i++) {
            $expressions[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
            );
        }

        return $expressions;
    }
}
