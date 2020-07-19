<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\RecurNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class RecurSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironment $env): RecurNode
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

    public function expressions(Tuple $tuple, NodeEnvironment $env): array
    {
        $expressions = [];

        for ($i = 1, $tupleCount = count($tuple); $i < $tupleCount; $i++) {
            $expressions[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
            );
        }

        return $expressions;
    }
}
