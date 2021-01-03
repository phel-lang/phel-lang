<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class ApplyNode extends AbstractNode
{
    private AbstractNode $fn;

    /** @var AbstractNode[] */
    private array $arguments;

    /**
     * @param AbstractNode[] $arguments
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $fn,
        array $arguments,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->fn = $fn;
        $this->arguments = $arguments;
    }

    public function getFn(): AbstractNode
    {
        return $this->fn;
    }

    /**
     * @return AbstractNode[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
