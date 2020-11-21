<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Lang\SourceLocation;
use Phel\Compiler\NodeEnvironment;

final class ApplyNode extends Node
{
    private Node $fn;

    /** @var Node[] */
    private array $arguments;

    /**
     * @param Node[] $arguments
     */
    public function __construct(
        NodeEnvironment $env,
        Node $fn,
        array $arguments,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->fn = $fn;
        $this->arguments = $arguments;
    }

    public function getFn(): Node
    {
        return $this->fn;
    }

    /**
     * @return Node[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
