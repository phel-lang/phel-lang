<?php


namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\Lang\Table;
use Phel\NodeEnvironment;

class DefNode extends Node
{

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var Symbol
     */
    protected $name;

    /**
     * @var Table
     */
    protected $meta;

    /**
     * @var Node
     */
    protected $init;

    public function __construct(NodeEnvironment $env, string $namespace, Symbol $name, Table $meta, Node $init, ?SourceLocation $sourceLocation = null)
    {
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
