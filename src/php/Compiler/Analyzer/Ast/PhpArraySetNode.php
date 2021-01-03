<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpArraySetNode extends AbstractNode
{
    private AbstractNode $arrayExpr;
    private AbstractNode $accessExpr;
    private AbstractNode $valueExpr;

    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $arrayExpr,
        AbstractNode $accessExpr,
        AbstractNode $valueExpr,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->arrayExpr = $arrayExpr;
        $this->accessExpr = $accessExpr;
        $this->valueExpr = $valueExpr;
    }

    public function getArrayExpr(): AbstractNode
    {
        return $this->arrayExpr;
    }

    public function getAccessExpr(): AbstractNode
    {
        return $this->accessExpr;
    }

    public function getValueExpr(): AbstractNode
    {
        return $this->valueExpr;
    }
}
