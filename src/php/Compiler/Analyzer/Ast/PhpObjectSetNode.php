<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpObjectSetNode extends AbstractNode
{
    private PhpObjectCallNode $leftExpr;
    private AbstractNode $rightExpr;

    public function __construct(
        NodeEnvironmentInterface $env,
        PhpObjectCallNode $leftExpr,
        AbstractNode $rightExpr,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->leftExpr = $leftExpr;
        $this->rightExpr = $rightExpr;
    }

    public function getLeftExpr(): PhpObjectCallNode
    {
        return $this->leftExpr;
    }

    public function getRightExpr(): AbstractNode
    {
        return $this->rightExpr;
    }
}
