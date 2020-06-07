<?php


namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class PropertyOrConstantAccessNode extends Node
{

    /**
     * @var Symbol
     */
    protected $name;

    public function __construct(NodeEnvironment $env, Symbol $name, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->name = $name;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }
}
