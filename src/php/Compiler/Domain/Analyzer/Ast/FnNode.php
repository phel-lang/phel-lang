<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class FnNode extends AbstractNode
{
    /**
     * @param list<Symbol> $params
     * @param list<Symbol> $uses
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private array $params,
        private AbstractNode $body,
        private array $uses,
        private bool $isVariadic,
        private bool $recurs,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
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

    public function getRecurs(): bool
    {
        return $this->recurs;
    }
}
