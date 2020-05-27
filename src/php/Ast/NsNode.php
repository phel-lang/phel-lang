<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class NsNode extends Node {

    /**
     * @var Symbol[]
     */
    protected $requireNs;

    /**
     * @param Symbol[] $requireNs
     */
    public function __construct(array $requireNs, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct(NodeEnvironment::empty());
        $this->requireNs = $requireNs;
    }

    /**
     * @return Symbol[]
     */
    public function getRequireNs() {
        return $this->requireNs;
    }
}