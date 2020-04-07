<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class FnNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Symbol[]
     */
    protected $params;

    /**
     * @var Node
     */
    protected $body;

    /**
     * @var NodeEnvironment
     */
    protected $uses;

    /**
     * @var bool
     */
    protected $isVariadic;

    /**
     * @var bool
     */
    protected $recurs;

    public function __construct(NodeEnvironment $env, array $params, Node $body, array $uses, bool $isVariadic, bool $recurs)
    {
        $this->env = $env;
        $this->params = $params;
        $this->body = $body;
        $this->uses = $uses;
        $this->isVariadic = $isVariadic;
        $this->recurs = $recurs;
    }

    public function getParams() {
        return $this->params;
    }

    public function getBody() {
        return $this->body;
    }

    public function getUses() {
        return $this->uses;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    public function isVariadic() {
        return $this->isVariadic;
    }

    public function getRecurs() {
        return $this->recurs;
    }
}