<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class DefStructNode extends AbstractNode
{
    private string $namespace;

    private Symbol $name;

    /** @var Symbol[] */
    private array $params;

    /** @var list<DefStructInterface> */
    private array $interfaces;

    /**
     * @param Symbol[] $params
     * @param list<DefStructInterface> $interfaces
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        string $namespace,
        Symbol $name,
        array $params,
        array $interfaces,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->namespace = $namespace;
        $this->name = $name;
        $this->params = $params;
        $this->interfaces = $interfaces;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return list<DefStructInterface>
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }
}
