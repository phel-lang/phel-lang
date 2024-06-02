<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

/**
 * @implements Fnable<AbstractNode>
 */
final class ApplyNode extends AbstractNode implements Fnable
{
    /**
     * @param list<AbstractNode> $args
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $fn,
        private readonly array $args,
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
    public function getArgs(): array
    {
        return $this->args;
    }
}
