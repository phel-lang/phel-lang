<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

/**
 * @internal
 */
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
        private readonly bool $inlineInExpression = false,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * A copy whose bindings, in expression position, emit as assignments
     * inside the expression rather than inside an IIFE. Only a producer that
     * knows the bindings may land in the enclosing PHP scope sets it; see
     * {@see \Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\UpdateLiteralFnLowering}.
     * A pass that rebuilds the node drops the flag, which only costs the IIFE.
     */
    public function withInlineInExpression(): self
    {
        return new self(
            $this->getEnv(),
            $this->bindings,
            $this->bodyExpr,
            $this->isLoop,
            $this->getStartSourceLocation(),
            true,
        );
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

    public function isInlineInExpression(): bool
    {
        return $this->inlineInExpression;
    }
}
