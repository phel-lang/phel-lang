<?php 

namespace Phel\Ast;

use Phel\Lang\Phel;
use Phel\NodeEnvironment;

class TryNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node
     */
    protected $body;

    /**
     * @var CatchNode[]
     */
    protected $catches;

    /**
     * @var Node|null
     */
    protected $finally;

    public function __construct(NodeEnvironment $env, Node $body, array $catches, $finally = null)
    {
        $this->env = $env;
        $this->body = $body;
        $this->catches = $catches;
        $this->finally = $finally;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    public function getBody() {
        return $this->body;
    }

    public function getCatches() {
        return $this->catches;
    }

    public function getFinally() {
        return $this->finally;
    }
}