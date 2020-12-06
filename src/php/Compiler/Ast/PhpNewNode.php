<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpNewNode extends Node
{
    private Node $classExpr;

    /** @var Node[] */
    private array $args;

    /**
     * @param Node[] $args
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        Node $classExpr,
        array $args,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->classExpr = $classExpr;
        $this->args = $args;
    }

    public function getClassExpr(): Node
    {
        return $this->classExpr;
    }

    /**
     * @return Node[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
