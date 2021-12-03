<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Lang\Symbol;

final class DefStructMethod
{
    private Symbol $name;
    private FnNode $fnNode;

    public function __construct(Symbol $name, FnNode $fnNode)
    {
        $this->name = $name;
        $this->fnNode = $fnNode;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    public function getFnNode(): FnNode
    {
        return $this->fnNode;
    }
}
