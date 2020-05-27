<?php 

namespace Phel\Ast;

use Phel\Lang\Phel;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class QuoteNode extends Node {

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param NodeEnvironment $env
     * @param mixed $value
     */
    public function __construct(NodeEnvironment $env, $value, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }
}