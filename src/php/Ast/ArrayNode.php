<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class ArrayNode extends Node
{

    /**
     * @var array
     */
    protected $values;

    /**
     * @param NodeEnvironment $env
     * @param array $value
     */
    public function __construct(NodeEnvironment $env, array $values, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->values = $values;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }
}
