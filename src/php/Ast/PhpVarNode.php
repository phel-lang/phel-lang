<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class PhpVarNode implements Node {

    const INFIX_OPERATORS = array(
        "+", 
        "-", 
        "*", 
        ".", 
        "/", 
        "%", 
        "=", 
        "=&", 
        "<", 
        ">", 
        "<=", 
        ">=", 
        "===", 
        "==", 
        "!=", 
        "!==", 
        "instanceof", 
        "|", 
        "&", 
        "**", 
        "^", 
        "<<", 
        ">>"
    );
    const CALLABLE_KEYWORDS = array(
        'array', 
        'die',
        'empty',
        'echo', 
        'print',
        'isset'
    );

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var string
     */
    protected $name;

    public function __construct(NodeEnvironment $env, string $name)
    {
        $this->env = $env;
        $this->name = $name;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    public function isInfix(): bool {
        return in_array($this->name, self::INFIX_OPERATORS);
    }

    public function isCallable(): bool {
        return \is_callable($this->name) || in_array($this->name, self::CALLABLE_KEYWORDS);
    }
}