<?php 

namespace Phel\Ast;

use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\Lang\Table;
use Phel\NodeEnvironment;

class GlobalVarNode extends Node {

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

    public function __construct(NodeEnvironment $env, string $namespace, Symbol $name, Table $meta, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->namespace = $namespace;
        $this->name = $name;
        $this->meta = $meta;
    }

    public function getNamespace(): string {
        return $this->namespace;
    }

    public function getName(): Symbol {
        return $this->name;
    }

    public function getMeta(): Table {
        return $this->meta;
    }

    public function isMacro(): bool {
        return $this->meta[new Keyword('macro')] === true;
    }
}