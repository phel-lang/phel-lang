<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Lang\Symbol;

final readonly class DefStructMethod
{
    public function __construct(
        private Symbol $name,
        private FnNode $fnNode,
    ) {
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
