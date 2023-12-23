<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpObjectSetNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly PhpObjectCallNode $leftExpr,
        private readonly AbstractNode $rightExpr,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
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
