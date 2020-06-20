<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\PhpArrayPushNode;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzePhpAPush
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): PhpArrayPushNode
    {
        return new PhpArrayPushNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $x->getStartLocation()
        );
    }
}
