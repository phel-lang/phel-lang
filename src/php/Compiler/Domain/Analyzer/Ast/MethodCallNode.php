<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

/**
 * @implements Fnable<Symbol>
 */
final class MethodCallNode extends AbstractNode implements Fnable
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
    public function getArguments(): array
    {
        return $this->args;
    }
}
