<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpNewNode extends AbstractNode
{
    private AbstractNode $classExpr;

    /** @var AbstractNode[] */
    private array $args;

    /**
     * @param AbstractNode[] $args
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $classExpr,
        array $args,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->classExpr = $classExpr;
        $this->args = $args;
    }

    public function getClassExpr(): AbstractNode
    {
        return $this->classExpr;
    }

    /**
     * @return AbstractNode[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
