<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

use function array_map;
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
}
