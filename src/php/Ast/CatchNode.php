<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class CatchNode extends Node {

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

    public function __construct(NodeEnvironment $env, Symbol $type, Symbol $name, Node $body, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->type = $type;
        $this->name = $name;
        $this->body = $body;
    }

    public function getType(): Symbol {
        return $this->type;
    }

    public function getName(): Symbol {
        return $this->name;
    }

    public function getBody(): Node {
        return $this->body;
    }
}