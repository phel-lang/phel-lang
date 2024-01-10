<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class LetNode extends AbstractNode
{
    /**
     * @param list<BindingNode> $bindings
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly array $bindings,
        private readonly AbstractNode $bodyExpr,
        private readonly bool $isLoop,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * @return list<BindingNode>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getBodyExpr(): AbstractNode
    {
        return $this->bodyExpr;
    }

    public function isLoop(): bool
    {
        return $this->isLoop;
    }
}
