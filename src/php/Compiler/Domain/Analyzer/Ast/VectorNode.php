<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class VectorNode extends AbstractNode
{
    /**
     * @param list<AbstractNode> $args
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly array $args,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * @return list<AbstractNode>
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
