<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Tuple;

final class PhpOSetSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): PhpObjectSetNode
    {
        $left = $this->analyzer->analyze($tuple[1], $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION));
        $right = $this->analyzer->analyze($tuple[2], $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION));

        if (!$left instanceof PhpObjectCallNode) {
            throw AnalyzerException::withLocation('First argument of php/oget must be a property access', $tuple);
        }

        if ($left->isMethodCall() === true) {
            throw AnalyzerException::withLocation('First argument of php/oget must be a property access', $tuple);
        }

        return new PhpObjectSetNode(
            $env,
            $left,
            $right,
            $tuple->getStartLocation()
        );
    }
}
