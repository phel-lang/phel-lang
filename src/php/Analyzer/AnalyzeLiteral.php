<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Ast\LiteralNode;
use Phel\Lang\Phel;
use Phel\NodeEnvironment;

final class AnalyzeLiteral
{
    public function __invoke($x, NodeEnvironment $env): LiteralNode
    {
        $sourceLocation = ($x instanceof Phel) ? $x->getStartLocation() : null;

        return new LiteralNode($env, $x, $sourceLocation);
    }
}
