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
        private readonly ?int $callerBindingCount = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * A copy marking where a spliced fn body starts: the first `$count`
     * bindings are the caller's code, and every later binding and the body
     * came from a literal `fn` spliced in place of a closure
     * ({@see \Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\UpdateLiteralFnLowering}).
     * A walker that stops at a fn body, as param type inference does, stops
     * there too. A pass that rebuilds the node must carry the mark, or decline.
     */
    public function withSplicedFnBodyAfter(int $count): self
    {
        return new self(
            $this->getEnv(),
            $this->bindings,
            $this->bodyExpr,
            $this->isLoop,
            $this->getStartSourceLocation(),
            $count,
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

    /**
     * How many leading bindings are the caller's own code, or `null` when
     * nothing in this `let` was spliced from a fn body.
     */
    public function getCallerBindingCount(): ?int
    {
        return $this->callerBindingCount;
    }
}
