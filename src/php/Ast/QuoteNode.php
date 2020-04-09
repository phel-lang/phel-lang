<?php 

namespace Phel\Ast;

use Phel\Lang\Phel;
use Phel\NodeEnvironment;

class QuoteNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var mixed
     */
    protected $value;

    public function __construct(NodeEnvironment $env, $value)
    {
        $this->env = $env;
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}