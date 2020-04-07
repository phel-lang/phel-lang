<?php 

namespace Phel\Ast;

use Phel\Lang\Phel;
use Phel\NodeEnvironment;

class LiteralNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Phel
     */
    protected $value;

    public function __construct(NodeEnvironment $env, Phel $value)
    {
        $this->env = $env;
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}