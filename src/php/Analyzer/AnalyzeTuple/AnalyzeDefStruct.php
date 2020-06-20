<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\DefStructNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeDefStruct
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): DefStructNode
    {
        if (count($x) !== 3) {
            throw new AnalyzerException(
                "Exactly two arguments are required for 'defstruct. Got " . count($x),
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Symbol)) {
            throw new AnalyzerException(
                "First arugment of 'defstruct must be a Symbol.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[2] instanceof Tuple)) {
            throw new AnalyzerException(
                "Second arugment of 'defstruct must be a Tuple.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $params = [];
        foreach ($x[2] as $element) {
            if (!($element instanceof Symbol)) {
                throw new AnalyzerException(
                    'Defstruct field elements must by Symbols.',
                    $element->getStartLocation(),
                    $element->getEndLocation()
                );
            }

            $params[] = $element;
        }

        $namespace = $this->analyzer->getGlobalEnvironment()->getNs();

        return new DefStructNode(
            $env,
            $namespace,
            $x[1],
            $params,
            $x->getStartLocation()
        );
    }
}
