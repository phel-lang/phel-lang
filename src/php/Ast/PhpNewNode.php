<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

final class PhpNewNode extends Node
{
    private Node $classExpr;

    /** @var Node[] */
    private array $args;

    /**
     * @param Node[] $args
     */
    public function __construct(
        NodeEnvironment $env,
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
