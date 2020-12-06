<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\WithAnalyzer;
use Phel\Compiler\Ast\PhpArraySetNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\Tuple;

final class PhpASetSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): PhpArraySetNode
    {
        return new PhpArraySetNode(
            $env,
            $this->analyzer->analyze($tuple[1], $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $this->analyzer->analyze($tuple[2], $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $this->analyzer->analyze($tuple[3], $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $tuple->getStartLocation()
        );
    }
}
