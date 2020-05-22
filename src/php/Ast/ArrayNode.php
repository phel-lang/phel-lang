<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class ArrayNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var array
     */
    protected $values;

    /**
     * @param NodeEnvironment $env
     * @param array $value
     */
    public function __construct(NodeEnvironment $env, array $values)
    {
        $this->env = $env;
        $this->values = $values;
    }

    /**
     * @return array
     */
    public function getValues() {
        return $this->values;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}