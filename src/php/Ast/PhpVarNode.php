<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class PhpVarNode implements Node {

    const INFIX_OPERATORS = array("+", "-", "*", ".", "/", "%", "=", "=&", "<", ">", "<=", ">=", "===", "==", "!=", "!==", "instanceof", "|", "&", "**");
    const EXTRA_CALLABLE = array('array', 'echo', 'print');

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var string
     */
    protected $name;

    public function __construct(NodeEnvironment $env, $name)
    {
        $this->env = $env;
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    public function isInfix() {
        return in_array($this->name, self::INFIX_OPERATORS);
    }

    public function isCallable() {
        return \is_callable($this->name) || in_array($this->name, self::EXTRA_CALLABLE);
    }
}