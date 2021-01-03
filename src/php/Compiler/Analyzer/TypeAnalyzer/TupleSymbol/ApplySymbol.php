<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Compiler\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\Tuple;

final class ApplySymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): ApplyNode
    {
        if (count($tuple) < 3) {
            throw AnalyzerException::withLocation("At least three arguments are required for 'apply", $tuple);
        }

        return new ApplyNode(
            $env,
            $this->fnExpr($tuple[1], $env),
            $this->arguments($tuple, $env),
            $tuple->getStartLocation()
        );
    }

    /**
     * Analyze the function expression of the apply special form.
     *
     * @param AbstractType|string|float|int|bool|null $x
     * @param NodeEnvironmentInterface $env
     *
     * @return AbstractNode
     */
    private function fnExpr($x, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->analyzer->analyze(
            $x,
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
        );
    }

    private function arguments(Tuple $x, NodeEnvironmentInterface $env): array
    {
        $args = [];
        for ($i = 2, $iMax = count($x); $i < $iMax; $i++) {
            $args[] = $this->fnExpr($x[$i], $env);
        }

        return $args;
    }
}
