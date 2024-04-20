<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class CallNode extends AbstractNode
{
    /**
     * @param list<AbstractNode> $arguments
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $fn,
        private readonly array $arguments,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getFn(): AbstractNode
    {
        return $this->fn;
    }

    /**
     * @return list<AbstractNode>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
