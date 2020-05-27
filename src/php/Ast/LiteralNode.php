<?php 

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Phel;
use Phel\NodeEnvironment;

class LiteralNode extends Node {

    /**
     * @var Phel|scalar|null
     */
    protected $value;

    /**
     * @param NodeEnvironment $env
     * @param Phel|scalar|null $value
     */
    public function __construct(NodeEnvironment $env, $value, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->value = $value;
    }

    /**
     * @return Phel|scalar|null
     */
    public function getValue() {
        return $this->value;
    }
}