<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

class CatchNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Symbol
     */
    protected $type;

    /**
     * @var Symbol
     */
    protected $name;

    /**
     * @var Node
     */
    protected $body;

    public function __construct(NodeEnvironment $env, Symbol $type, Symbol $name, Node $body)
    {
        $this->env = $env;
        $this->type = $type;
        $this->name = $name;
        $this->body = $body;
    }

    public function getType() {
        return $this->type;
    }

    public function getName() {
        return $this->name;
    }

    public function getBody() {
        return $this->body;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}