<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class LetNode extends AbstractNode
{
    /** @var BindingNode[] */
    private array $bindings;

    private AbstractNode $bodyExpr;

    private bool $isLoop;

    /**
     * @param BindingNode[] $bindings
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        array $bindings,
        AbstractNode $bodyExpr,
        bool $isLoop,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->bindings = $bindings;
        $this->bodyExpr = $bodyExpr;
        $this->isLoop = $isLoop;
    }

    /**
     * @return BindingNode[]
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
