<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class ForeachNode extends AbstractNode
{
    private AbstractNode $bodyExpr;
    private AbstractNode $listExpr;
    private Symbol $valueSymbol;
    private ?Symbol $keySymbol;

    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $bodyExpr,
        AbstractNode $listExpr,
        Symbol $valueSymbol,
        ?Symbol $keySymbol = null,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->bodyExpr = $bodyExpr;
        $this->listExpr = $listExpr;
        $this->valueSymbol = $valueSymbol;
        $this->keySymbol = $keySymbol;
    }

    public function getBodyExpr(): AbstractNode
    {
        return $this->bodyExpr;
    }

    public function getListExpr(): AbstractNode
    {
        return $this->listExpr;
    }

    public function getValueSymbol(): Symbol
    {
        return $this->valueSymbol;
    }

    public function getKeySymbol(): ?Symbol
    {
        return $this->keySymbol;
    }
}
