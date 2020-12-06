<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Compiler\RecurFrame;
use Phel\Lang\SourceLocation;

final class RecurNode extends Node
{
    private RecurFrame $frame;

    /** @var Node[] */
    private array $expressions;

    /**
     * @param Node[] $expressions
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
     * @return Node[]
     */
    public function getExpressions(): array
    {
        return $this->expressions;
    }
}
