<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class FnNode extends AbstractNode
{
    /** @var Symbol[] */
    private array $params;

    private AbstractNode $body;

    /** @var Symbol[] */
    private array $uses;

    private bool $isVariadic;

    private bool $recurs;

    /**
     * @param Symbol[] $params
     * @param Symbol[] $uses
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        array $params,
        AbstractNode $body,
        array $uses,
        bool $isVariadic,
        bool $recurs,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->params = $params;
        $this->body = $body;
        $this->uses = $uses;
        $this->isVariadic = $isVariadic;
        $this->recurs = $recurs;
    }

    /**
     * @return Symbol[]
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
     * @return Symbol[]
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
