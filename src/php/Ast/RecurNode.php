<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;
use Phel\RecurFrame;

final class RecurNode extends Node
{
    private RecurFrame $frame;

    /** @var Node[] */
    private array $expressions;

    /**
     * @param Node[] $expressions
     */
    public function __construct(
        NodeEnvironment $env,
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
