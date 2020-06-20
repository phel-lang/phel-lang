<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer;
use Phel\Ast\PhpArraySetNode;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzePhpASet
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke(Tuple $x, NodeEnvironment $env): PhpArraySetNode
    {
        return new PhpArraySetNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[3], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $x->getStartLocation()
        );
    }
}
