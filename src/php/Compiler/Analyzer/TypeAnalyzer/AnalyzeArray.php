<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Analyzer\Ast\ArrayNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;

final class AnalyzeArray
{
    use WithAnalyzerTrait;

    public function analyze(PhelArray $array, NodeEnvironmentInterface $env): ArrayNode
    {
        $values = [];
        $valueEnv = $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION);

        /** @var AbstractType|string|float|int|bool|null $value */
        foreach ($array as $value) {
            $values[] = $this->analyzer->analyze($value, $valueEnv);
        }

        return new ArrayNode($env, $values, $array->getStartLocation());
    }
}
