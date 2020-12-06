<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\WithAnalyzer;
use Phel\Compiler\Ast\PhpArrayUnsetNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;

final class PhpAUnsetSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): PhpArrayUnsetNode
    {
        if ($env->getContext() !== NodeEnvironmentInterface::CONTEXT_STATEMENT) {
            throw AnalyzerException::withLocation("'php/unset can only be called as Statement and not as Expression", $tuple);
        }

        return new PhpArrayUnsetNode(
            $env,
            $this->analyzer->analyze($tuple[1], $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $this->analyzer->analyze($tuple[2], $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $tuple->getStartLocation()
        );
    }
}
