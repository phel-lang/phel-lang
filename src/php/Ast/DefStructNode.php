<?php 

namespace Phel\Ast;

use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\Lang\Table;
use Phel\NodeEnvironment;

class DefStructNode extends Node {

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var Symbol
     */
    protected $name;

    /**
     * @var Symbol[]
     */
    protected $params;

    public function __construct(NodeEnvironment $env, string $namespace, Symbol $name, array $params, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->namespace = $namespace;
        $this->name = $name;
        $this->params = $params;
    }

    public function getNamespace(): string {
        return $this->namespace;
    }

    public function getName(): Symbol {
        return $this->name;
    }

    public function getParams(): array {
        return $this->params;
    }

    public function getParamsAsKeywords(): array {
        $result = [];
        foreach ($this->params as $param) {
            $keyword = new Keyword($param->getName());
            $keyword->setStartLocation($this->getStartSourceLocation());
            $result[] = $keyword;
        }

        return $result;
    }
}