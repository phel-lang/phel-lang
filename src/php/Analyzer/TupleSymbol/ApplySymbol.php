<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\ApplyNode;
use Phel\Ast\Node;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class ApplySymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $tuple, NodeEnvironment $env): ApplyNode
    {
        if (count($tuple) < 3) {
            throw AnalyzerException::withLocation("At least three arguments are required for 'apply", $tuple);
        }

        return new ApplyNode(
            $env,
            $this->analyze($tuple[1], $env),
            $this->arguments($tuple, $env),
            $tuple->getStartLocation()
        );
    }

    private function analyze($x, NodeEnvironment $env): Node
    {
        return $this->analyzer->analyze(
            $x,
            $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
        );
    }

    private function arguments(Tuple $x, NodeEnvironment $env): array
    {
        $args = [];
        for ($i = 2, $iMax = count($x); $i < $iMax; $i++) {
            $args[] = $this->analyze($x[$i], $env);
        }

        return $args;
    }
}
