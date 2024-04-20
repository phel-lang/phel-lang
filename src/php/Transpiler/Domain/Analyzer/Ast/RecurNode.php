<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

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
