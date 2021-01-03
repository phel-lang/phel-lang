<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class IfNode extends AbstractNode
{
    private AbstractNode $testExpr;
    private AbstractNode $thenExpr;
    private AbstractNode $elseExpr;

    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $testExpr,
        AbstractNode $thenExpr,
        AbstractNode $elseExpr,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->testExpr = $testExpr;
        $this->thenExpr = $thenExpr;
        $this->elseExpr = $elseExpr;
    }

    public function getTestExpr(): AbstractNode
    {
        return $this->testExpr;
    }

    public function getThenExpr(): AbstractNode
    {
        return $this->thenExpr;
    }

    public function getElseExpr(): AbstractNode
    {
        return $this->elseExpr;
    }
}
