<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\ReadModel;

use Phel\Compiler\Ast\Node;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\Symbol;

final class ForeachSymbolTuple
{
    private array $lets;
    private NodeEnvironmentInterface $bodyEnv;
    private Node $listExpr;
    private Symbol $valueSymbol;
    private ?Symbol $keySymbol;

    public function __construct(
        array $lets,
        NodeEnvironmentInterface $bodyEnv,
        Node $listExpr,
        Symbol $valueSymbol,
        ?Symbol $keySymbol = null
    ) {
        $this->lets = $lets;
        $this->bodyEnv = $bodyEnv;
        $this->listExpr = $listExpr;
        $this->valueSymbol = $valueSymbol;
        $this->keySymbol = $keySymbol;
    }

    public function lets(): array
    {
        return $this->lets;
    }

    public function bodyEnv(): NodeEnvironmentInterface
    {
        return $this->bodyEnv;
    }

    public function listExpr(): Node
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
