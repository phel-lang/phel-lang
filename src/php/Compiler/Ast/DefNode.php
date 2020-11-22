<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironment;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\Table;

final class DefNode extends Node
{
    private string $namespace;
    private Symbol $name;
    private Table $meta;
    private Node $init;

    public function __construct(
        NodeEnvironment $env,
        string $namespace,
        Symbol $name,
        Table $meta,
        Node $init,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->namespace = $namespace;
        $this->name = $name;
        $this->meta = $meta;
        $this->init = $init;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    public function getMeta(): Table
    {
        return $this->meta;
    }

    public function getInit(): Node
    {
        return $this->init;
    }
}
