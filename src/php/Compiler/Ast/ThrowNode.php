<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class ThrowNode extends AbstractNode
{
    private AbstractNode $exceptionExpr;

    public function __construct(NodeEnvironmentInterface $env, AbstractNode $exceptionExpr, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->exceptionExpr = $exceptionExpr;
    }

    public function getExceptionExpr(): AbstractNode
    {
        return $this->exceptionExpr;
    }
}
