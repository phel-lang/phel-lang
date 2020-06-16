<?php


namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class NsNode extends Node
{
    /**
     * @var Symbol[]
     */
    protected array $requireNs;

    /**
     * @var string
     */
    protected string $namespace;

    /**
     * @param Symbol[] $requireNs
     */
    public function __construct(string $namespace, array $requireNs, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct(NodeEnvironment::empty(), $sourceLocation);
        $this->requireNs = $requireNs;
        $this->namespace = $namespace;
    }

    /**
     * @return Symbol[]
     */
    public function getRequireNs(): array
    {
        return $this->requireNs;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
