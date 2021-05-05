<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\TypeInterface;

final class AnalyzeLiteral
{
    /**
     * @param TypeInterface|string|float|int|bool|null $value
     */
    public function analyze($value, NodeEnvironmentInterface $env): LiteralNode
    {
        $sourceLocation = ($value instanceof TypeInterface)
            ? $value->getStartLocation()
            : null;

        return new LiteralNode($env, $value, $sourceLocation);
    }
}
