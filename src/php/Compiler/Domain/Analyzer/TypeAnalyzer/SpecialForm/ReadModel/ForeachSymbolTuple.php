<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReadModel;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Symbol;

final readonly class ForeachSymbolTuple
{
    public function __construct(
        private array $lets,
        private NodeEnvironmentInterface $bodyEnv,
        private AbstractNode $listExpr,
        private Symbol $valueSymbol,
        private ?Symbol $keySymbol = null,
    ) {
    }

    public function lets(): array
    {
        return $this->lets;
    }

    public function bodyEnv(): NodeEnvironmentInterface
    {
        return $this->bodyEnv;
    }

    public function listExpr(): AbstractNode
    {
        return $this->listExpr;
    }

    public function valueSymbol(): Symbol
    {
        return $this->valueSymbol;
    }

    public function keySymbol(): ?Symbol
    {
        return $this->keySymbol;
    }
}
