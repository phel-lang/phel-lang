<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class MapNode extends AbstractNode
{
    private array $keyValues;

    public function __construct(NodeEnvironmentInterface $env, array $keyValues, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->keyValues = $keyValues;
    }

    public function getKeyValues(): array
    {
        return $this->keyValues;
    }
}
