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
     * @var Phel|scalar|null
     */
    protected $value;

    /**
     * @param NodeEnvironment $env
     * @param Phel|scalar|null $value
     */
    public function __construct(NodeEnvironment $env, $value)
    {
        $this->env = $env;
        $this->value = $value;
    }

    /**
     * @return Phel|scalar|null
     */
    public function getValue() {
        return $this->value;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}