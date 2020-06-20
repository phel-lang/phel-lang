<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer;
use Phel\Ast\PhpArrayUnsetNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzePhpAUnset
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke(Tuple $x, NodeEnvironment $env): PhpArrayUnsetNode
    {
        if ($env->getContext() !== NodeEnvironment::CTX_STMT) {
            throw new AnalyzerException(
                "'php/unset can only be called as Statement and not as Expression",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        return new PhpArrayUnsetNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $x->getStartLocation()
        );
    }
}
