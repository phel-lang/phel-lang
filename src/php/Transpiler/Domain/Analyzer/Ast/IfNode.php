<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class IfNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $testExpr,
        private readonly AbstractNode $thenExpr,
        private readonly AbstractNode $elseExpr,
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
