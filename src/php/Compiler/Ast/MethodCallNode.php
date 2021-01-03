<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class MethodCallNode extends AbstractNode
{
    private Symbol $fn;
    private array $args;

    /**
     * @param AbstractNode[] $args
     */
    public function __construct(NodeEnvironmentInterface $env, Symbol $fn, array $args, ?SourceLocation $sourceLocation = null)
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
     * @return AbstractNode[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
