<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

final class NsNode extends Node
{
    /** @var Symbol[] */
    private array $requireNs;

    private string $namespace;

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
