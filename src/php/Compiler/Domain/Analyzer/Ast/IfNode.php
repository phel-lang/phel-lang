<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class IfNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private AbstractNode $testExpr,
        private AbstractNode $thenExpr,
        private AbstractNode $elseExpr,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
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
