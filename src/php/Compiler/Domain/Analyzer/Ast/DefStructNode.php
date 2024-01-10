<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class DefStructNode extends AbstractNode
{
    /**
     * @param list<Symbol> $params
     * @param list<DefStructInterface> $interfaces
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly string $namespace,
        private readonly Symbol $name,
        private readonly array $params,
        private readonly array $interfaces,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
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
