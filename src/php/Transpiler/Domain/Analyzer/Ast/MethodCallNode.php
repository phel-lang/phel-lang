<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class MethodCallNode extends AbstractNode
{
    /**
     * @param list<AbstractNode> $args
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly Symbol $fn,
        private readonly array $args,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getFn(): Symbol
    {
        return $this->fn;
    }

    /**
     * @return list<AbstractNode>
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
