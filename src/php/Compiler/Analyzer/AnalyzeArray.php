<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer;

use Phel\Compiler\Ast\ArrayNode;
use Phel\Compiler\NodeEnvironment;
use Phel\Lang\PhelArray;

final class AnalyzeArray
{
    use WithAnalyzer;

    public function analyze(PhelArray $array, NodeEnvironment $env): ArrayNode
    {
        $values = [];
        $valueEnv = $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION);

        foreach ($array as $value) {
            $values[] = $this->analyzer->analyze($value, $valueEnv);
        }

        return new ArrayNode($env, $values, $array->getStartLocation());
    }
}
