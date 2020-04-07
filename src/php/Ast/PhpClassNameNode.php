<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

class PhpClassNameNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Symbol
     */
    protected $name;

    public function __construct(NodeEnvironment $env, Symbol $name)
    {
        $this->env = $env;
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    
}