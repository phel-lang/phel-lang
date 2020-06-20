<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

final class TupleNode extends Node
{
    /** @var Node[] */
    private array $args;

    /**
     * @param Node[] $args
     */
    public function __construct(NodeEnvironment $env, array $args, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->args = $args;
    }

    /**
     * @return Node[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
