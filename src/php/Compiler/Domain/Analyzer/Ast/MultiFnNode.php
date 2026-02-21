<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

use function array_map;
use function array_reduce;
use function max;
use function min;

final class MultiFnNode extends AbstractNode
{
    /**
     * @param list<FnNode> $fnNodes
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly array $fnNodes,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * @return list<FnNode>
     */
    public function getFnNodes(): array
    {
        return $this->fnNodes;
    }

    public function getMinArity(): int
    {
        return min(array_map(static fn (FnNode $n): int => $n->getMinArity(), $this->fnNodes));
    }

    /**
     * Returns the maximum arity, or null if the function is variadic.
     */
    public function getMaxArity(): ?int
    {
        if ($this->isVariadic()) {
            return null;
        }

        return max(array_map(static fn (FnNode $n): int => $n->getMinArity(), $this->fnNodes));
    }

    /**
     * Returns true if any of the function overloads is variadic.
     */
    public function isVariadic(): bool
    {
        return array_reduce(
            $this->fnNodes,
            static fn (bool $carry, FnNode $n): bool => $carry || $n->isVariadic(),
            false,
        );
    }
}
