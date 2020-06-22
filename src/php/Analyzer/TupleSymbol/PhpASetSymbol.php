<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\PhpArraySetNode;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class PhpASetSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $tuple, NodeEnvironment $env): PhpArraySetNode
    {
        return new PhpArraySetNode(
            $env,
            $this->analyzer->analyze($tuple[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($tuple[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($tuple[3], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $tuple->getStartLocation()
        );
    }
}
