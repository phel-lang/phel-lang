<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\PhpArrayUnsetNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class PhpAUnsetSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironment $env): PhpArrayUnsetNode
    {
        if ($env->getContext() !== NodeEnvironment::CONTEXT_STATEMENT) {
            throw AnalyzerException::withLocation("'php/unset can only be called as Statement and not as Expression", $tuple);
        }

        return new PhpArrayUnsetNode(
            $env,
            $this->analyzer->analyze($tuple[1], $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)),
            $this->analyzer->analyze($tuple[2], $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)),
            $tuple->getStartLocation()
        );
    }
}
