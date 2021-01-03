<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class RecurNode extends AbstractNode
{
    private RecurFrame $frame;

    /** @var AbstractNode[] */
    private array $expressions;

    /**
     * @param AbstractNode[] $expressions
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        RecurFrame $frame,
        array $expressions,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->frame = $frame;
        $this->expressions = $expressions;
    }

    public function getFrame(): RecurFrame
    {
        return $this->frame;
    }

    /**
     * @return AbstractNode[]
     */
    public function getExpressions(): array
    {
        return $this->expressions;
    }
}
