<?php 

namespace Phel\Ast;

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
     * @var ?Node
     */
    protected $finally;

    /**
     * @param NodeEnvironment $env
     * @param Node $body
     * @param CatchNode[] $catches
     * @param ?Node $finally
     */
    public function __construct(NodeEnvironment $env, Node $body, array $catches, ?Node $finally = null)
    {
        $this->env = $env;
        $this->body = $body;
        $this->catches = $catches;
        $this->finally = $finally;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    public function getBody(): Node {
        return $this->body;
    }

    /**
     * @return CatchNode[]
     */
    public function getCatches() {
        return $this->catches;
    }

    public function getFinally(): ?Node {
        return $this->finally;
    }
}