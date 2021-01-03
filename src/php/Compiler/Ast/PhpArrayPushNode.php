<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpArrayPushNode extends AbstractNode
{
    private AbstractNode $arrayExpr;
    private AbstractNode $valueExpr;

    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $arrayExpr,
        AbstractNode $valueExpr,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->arrayExpr = $arrayExpr;
        $this->valueExpr = $valueExpr;
    }

    public function getArrayExpr(): AbstractNode
    {
        return $this->arrayExpr;
    }

    public function getValueExpr(): AbstractNode
    {
        return $this->valueExpr;
    }
}
