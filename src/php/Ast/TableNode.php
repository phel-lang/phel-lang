<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class TableNode extends Node
{

    /**
     * @var array
     */
    protected $keyValues;

    /**
     * @param NodeEnvironment $env
     * @param array $value
     */
    public function __construct(NodeEnvironment $env, array $keyValues, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->keyValues = $keyValues;
    }

    /**
     * @return array
     */
    public function getKeyValues()
    {
        return $this->keyValues;
    }
}
