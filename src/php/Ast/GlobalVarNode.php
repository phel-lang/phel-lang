<?php 

namespace Phel\Ast;

use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\NodeEnvironment;

class GlobalVarNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var Symbol
     */
    protected $name;

    /**
     * @var Table
     */
    protected $meta;

    public function __construct(NodeEnvironment $env, string $namespace, Symbol $name, Table $meta)
    {
        $this->env = $env;
        $this->namespace = $namespace;
        $this->name = $name;
        $this->meta = $meta;
    }

    public function getNamespace() {
        return $this->namespace;
    }

    public function getName() {
        return $this->name;
    }

    public function getMeta() {
        return $this->meta;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    public function isMacro() {
        return $this->meta[new Keyword('macro')] === true;
    }
}