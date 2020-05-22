<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class TableNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var array
     */
    protected $keyValues;

    /**
     * @param NodeEnvironment $env
     * @param array $value
     */
    public function __construct(NodeEnvironment $env, array $keyValues)
    {
        $this->env = $env;
        $this->keyValues = $keyValues;
    }

    /**
     * @return array
     */
    public function getKeyValues() {
        return $this->keyValues;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}