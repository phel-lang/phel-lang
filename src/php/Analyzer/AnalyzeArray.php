<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Analyzer;
use Phel\Ast\ArrayNode;
use Phel\Lang\PhelArray;
use Phel\NodeEnvironment;

final class AnalyzeArray
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke(PhelArray $phelArray, NodeEnvironment $env): ArrayNode
    {
        $values = [];
        $valueEnv = $env->withContext(NodeEnvironment::CTX_EXPR);
        foreach ($phelArray as $value) {
            $values[] = $this->analyzer->analyze($value, $valueEnv);
        }

        return new ArrayNode($env, $values, $phelArray->getStartLocation());
    }
}
