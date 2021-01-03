<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Compiler\Ast\IfNode;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;

/**
 * (if test then else?).
 */
final class IfSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): IfNode
    {
        $this->verifyArguments($tuple);

        return new IfNode(
            $env,
            $this->testExpression($tuple, $env),
            $this->thenExpression($tuple, $env),
            $this->elseExpression($tuple, $env),
            $tuple->getStartLocation()
        );
    }

    private function verifyArguments(Tuple $tuple): void
    {
        $tupleCount = count($tuple);

        if ($tupleCount < 3 || $tupleCount > 4) {
            throw AnalyzerException::withLocation("'if requires two or three arguments", $tuple);
        }
    }

    private function testExpression(Tuple $tuple, NodeEnvironmentInterface $env): AbstractNode
    {
        $envWithDisallowRecurFrame = $env
            ->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)
            ->withDisallowRecurFrame();

        return $this->analyzer->analyze($tuple[1], $envWithDisallowRecurFrame);
    }

    private function thenExpression(Tuple $tuple, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->analyzer->analyze($tuple[2], $env);
    }

    private function elseExpression(Tuple $tuple, NodeEnvironmentInterface $env): AbstractNode
    {
        if (count($tuple) === 3) {
            return $this->analyzer->analyze(null, $env);
        }

        return $this->analyzer->analyze($tuple[3], $env);
    }
}
