<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class FnNode extends Node {

    /**
     * @var Symbol[]
     */
    protected $params;

    /**
     * @var Node
     */
    protected $body;

    /**
     * @var Symbol[]
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

    /**
     * @param NodeEnvironment $env
     * @param Symbol[] $params
     * @param Node $body
     * @param Symbol[] $uses
     * @param bool $isVariadic
     * @param bool $recurs
     */
    public function __construct(NodeEnvironment $env, array $params, Node $body, array $uses, bool $isVariadic, bool $recurs, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->params = $params;
        $this->body = $body;
        $this->uses = $uses;
        $this->isVariadic = $isVariadic;
        $this->recurs = $recurs;
    }

    /**
     * @return Symbol[]
     */
    public function getParams() {
        return $this->params;
    }

    public function getBody(): Node {
        return $this->body;
    }

    /**
     * @return Symbol[]
     */
    public function getUses() {
        return $this->uses;
    }

    public function isVariadic(): bool {
        return $this->isVariadic;
    }

    public function getRecurs(): bool {
        return $this->recurs;
    }
}