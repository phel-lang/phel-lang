<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class SetVarNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $symbol,
        private readonly AbstractNode $valueExpr,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getSymbol(): AbstractNode
    {
        return $this->symbol;
    }

    public function getValueExpr(): AbstractNode
    {
        return $this->valueExpr;
    }
}
