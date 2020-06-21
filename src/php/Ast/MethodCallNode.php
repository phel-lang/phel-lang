<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

final class MethodCallNode extends Node
{
    private Symbol $fn;
    private array $args;

    /**
     * @param Node[] $args
     */
    public function __construct(NodeEnvironment $env, Symbol $fn, array $args, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->fn = $fn;
        $this->args = $args;
    }

    public function getFn(): Symbol
    {
        return $this->fn;
    }

    /**
     * @return Node[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
