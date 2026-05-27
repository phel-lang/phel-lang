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
        private readonly ?self $meta = null,
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

    /**
     * Reader-attached metadata (`^{:k v} {…}`) for this map literal. Distinct
     * from a node's `getMeta()` (which doesn't exist on `AbstractNode`).
     */
    public function getLiteralMeta(): ?self
    {
        return $this->meta;
    }
}
