<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class ForeachNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $bodyExpr,
        private readonly AbstractNode $listExpr,
        private readonly Symbol $valueSymbol,
        private readonly ?Symbol $keySymbol = null,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
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
