<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class RecurNode extends AbstractNode
{
    /**
     * @param list<AbstractNode> $expressions
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly RecurFrame $frame,
        private readonly array $expressions,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getFrame(): RecurFrame
    {
        return $this->frame;
    }

    /**
     * @return list<AbstractNode>
     */
    public function getExpressions(): array
    {
        return $this->expressions;
    }
}
