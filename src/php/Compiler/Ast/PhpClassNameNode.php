<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class PhpClassNameNode extends AbstractNode
{
    private Symbol $name;

    public function __construct(NodeEnvironmentInterface $env, Symbol $name, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->name = $name;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }
}
