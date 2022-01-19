<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class DefNode extends AbstractNode
{
    private string $namespace;
    private Symbol $name;
    private MapNode $meta;
    private AbstractNode $init;

    public function __construct(
        NodeEnvironmentInterface $env,
        string $namespace,
        Symbol $name,
        MapNode $meta,
        AbstractNode $init,
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

    public function getMeta(): MapNode
    {
        return $this->meta;
    }

    public function getInit(): AbstractNode
    {
        return $this->init;
    }
}
