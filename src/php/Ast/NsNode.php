<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

class NsNode implements Node {

    /**
     * @var Symbol[]
     */
    protected $requireNs;

    /**
     * @param Symbol[] $requireNs
     */
    public function __construct(array $requireNs)
    {
        $this->requireNs = $requireNs;
    }

    /**
     * @return Symbol[]
     */
    public function getRequireNs() {
        return $this->requireNs;
    }

    public function getEnv(): NodeEnvironment {
        return NodeEnvironment::empty();
    }
}