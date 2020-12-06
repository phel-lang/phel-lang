<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer;

use Phel\Compiler\Ast\ArrayNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\PhelArray;

final class AnalyzeArray
{
    use WithAnalyzer;

    public function analyze(PhelArray $array, NodeEnvironmentInterface $env): ArrayNode
    {
        $values = [];
        $valueEnv = $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION);

        foreach ($array as $value) {
            $values[] = $this->analyzer->analyze($value, $valueEnv);
        }

        return new ArrayNode($env, $values, $array->getStartLocation());
    }
}
