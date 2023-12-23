<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class BindingNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly Symbol $symbol,
        private readonly Symbol $shadow,
        private readonly AbstractNode $initExpr,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getSymbol(): Symbol
    {
        return $this->symbol;
    }

    public function getInitExpr(): AbstractNode
    {
        return $this->initExpr;
    }

    public function getShadow(): Symbol
    {
        return $this->shadow;
    }
}
