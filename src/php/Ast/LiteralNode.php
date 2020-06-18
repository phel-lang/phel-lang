<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\AbstractType;
use Phel\NodeEnvironment;

class LiteralNode extends Node
{

    /**
     * @var AbstractType|scalar|null
     */
    protected $value;

    /**
     * @param NodeEnvironment $env
     * @param AbstractType|scalar|null $value
     */
    public function __construct(NodeEnvironment $env, $value, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->value = $value;
    }

    /**
     * @return AbstractType|scalar|null
     */
    public function getValue()
    {
        return $this->value;
    }
}
