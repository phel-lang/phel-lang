<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Tuple;

final class ThrowSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): ThrowNode
    {
        if (count($tuple) !== 2) {
            throw AnalyzerException::withLocation("Exact one argument is required for 'throw", $tuple);
        }

        return new ThrowNode(
            $env,
            $this->analyzer->analyze($tuple[1], $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()),
            $tuple->getStartLocation()
        );
    }
}
