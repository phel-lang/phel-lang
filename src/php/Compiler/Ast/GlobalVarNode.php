<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Compiler\NodeEnvironment;

final class GlobalVarNode extends Node
{
    private string $namespace;
    private Symbol $name;
    private Table $meta;

    public function __construct(
        NodeEnvironment $env,
        string $namespace,
        Symbol $name,
        Table $meta,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->namespace = $namespace;
        $this->name = $name;
        $this->meta = $meta;
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

    public function isMacro(): bool
    {
        return $this->meta[new Keyword('macro')] === true;
    }
}
