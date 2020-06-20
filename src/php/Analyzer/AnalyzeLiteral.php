<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Ast\LiteralNode;
use Phel\Lang\AbstractType;
use Phel\NodeEnvironment;

final class AnalyzeLiteral
{
    /** @param AbstractType|scalar|null $x */
    public function __invoke($x, NodeEnvironment $env): LiteralNode
    {
        $sourceLocation = ($x instanceof AbstractType) ? $x->getStartLocation() : null;

        return new LiteralNode($env, $x, $sourceLocation);
    }
}
