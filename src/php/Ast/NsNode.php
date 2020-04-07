<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class NsNode implements Node {

    /**
     * @var Symbol[]
     */
    protected $requireNs;

    public function __construct(array $requireNs)
    {
        $this->requireNs = $requireNs;
    }

    public function getRequireNs() {
        return $this->requireNs;
    }

    public function getEnv(): NodeEnvironment {
        return NodeEnvironment::empty();
    }
}