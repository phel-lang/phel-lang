<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhelArrayNode extends AbstractNode
{
    /** @var AbstractNode[] */
    private array $args;

    /**
     * @param AbstractNode[] $args
     */
    public function __construct(NodeEnvironmentInterface $env, array $args, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->args = $args;
    }

    /**
     * @return AbstractNode[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
