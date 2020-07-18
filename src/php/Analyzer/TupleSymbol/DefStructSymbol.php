<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\DefStructNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class DefStructSymbol
{
    use WithAnalyzer;

    public function toNode(Tuple $tuple, NodeEnvironment $env): DefStructNode
    {
        if (count($tuple) !== 3) {
            throw AnalyzerException::withLocation(
                "Exactly two arguments are required for 'defstruct. Got " . count($tuple),
                $tuple
            );
        }

        if (!($tuple[1] instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'defstruct must be a Symbol.", $tuple);
        }

        if (!($tuple[2] instanceof Tuple)) {
            throw AnalyzerException::withLocation("Second argument of 'defstruct must be a Tuple.", $tuple);
        }

        return new DefStructNode(
            $env,
            $this->analyzer->getGlobalEnvironment()->getNs(),
            $tuple[1],
            $this->params($tuple[2]),
            $tuple->getStartLocation()
        );
    }

    private function params(Tuple $tuple): array
    {
        $params = [];
        foreach ($tuple as $element) {
            if (!($element instanceof Symbol)) {
                throw AnalyzerException::withLocation('Defstruct field elements must be Symbols.', $tuple);
            }
            $params[] = $element;
        }

        return $params;
    }
}
