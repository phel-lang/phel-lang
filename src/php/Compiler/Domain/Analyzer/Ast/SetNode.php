<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class SetNode extends AbstractNode
{
    /**
     * @param list<AbstractNode> $values
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly array $values,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * @return list<AbstractNode>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
