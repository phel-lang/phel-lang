<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class MapNode extends AbstractNode
{
    /**
     * @param array<int, AbstractNode> $keyValues
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly array $keyValues,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * @return array<int, AbstractNode>
     */
    public function getKeyValues(): array
    {
        return $this->keyValues;
    }
}
