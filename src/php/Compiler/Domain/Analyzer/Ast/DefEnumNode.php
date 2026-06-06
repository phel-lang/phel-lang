<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class DefEnumNode extends AbstractNode
{
    /**
     * @param list<DefEnumCase>        $cases
     * @param list<DefStructInterface> $interfaces
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly string $namespace,
        private readonly Symbol $name,
        private readonly array $cases,
        private readonly ?string $backingType,
        private readonly array $interfaces = [],
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

    /**
     * @return list<DefEnumCase>
     */
    public function getCases(): array
    {
        return $this->cases;
    }

    /**
     * The native PHP backing type (`int`/`string`), or null for a pure enum.
     */
    public function getBackingType(): ?string
    {
        return $this->backingType;
    }

    /**
     * @return list<DefStructInterface>
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }
}
