<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function count;

final class FnNode extends AbstractNode
{
    /**
     * @param list<Symbol> $params
     * @param list<Symbol> $uses
     */
    private bool $isDefinition = false;

    private bool $isMultiArityChild = false;

    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly array $params,
        private readonly AbstractNode $body,
        private readonly array $uses,
        private readonly bool $isVariadic,
        private readonly bool $recurs,
        ?SourceLocation $sourceLocation = null,
        private readonly ?Symbol $name = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getName(): ?Symbol
    {
        return $this->name;
    }

    public function markAsMultiArityChild(): self
    {
        $this->isMultiArityChild = true;

        return $this;
    }

    public function isMultiArityChild(): bool
    {
        return $this->isMultiArityChild;
    }

    public function markAsDefinition(): self
    {
        $this->isDefinition = true;

        return $this;
    }

    public function isDefinition(): bool
    {
        return $this->isDefinition;
    }

    /**
     * @return list<Symbol>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getBody(): AbstractNode
    {
        return $this->body;
    }

    /**
     * @return list<Symbol>
     */
    public function getUses(): array
    {
        return $this->uses;
    }

    public function isVariadic(): bool
    {
        return $this->isVariadic;
    }

    public function getMinArity(): int
    {
        $arity = count($this->params);
        if ($this->isVariadic) {
            --$arity;
        }

        return $arity;
    }

    public function getRecurs(): bool
    {
        return $this->recurs;
    }
}
