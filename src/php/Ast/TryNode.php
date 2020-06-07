<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class TryNode extends Node
{

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
    public function __construct(NodeEnvironment $env, Node $body, array $catches, ?Node $finally = null, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->body = $body;
        $this->catches = $catches;
        $this->finally = $finally;
    }

    public function getBody(): Node
    {
        return $this->body;
    }

    /**
     * @return CatchNode[]
     */
    public function getCatches()
    {
        return $this->catches;
    }

    public function getFinally(): ?Node
    {
        return $this->finally;
    }
}
