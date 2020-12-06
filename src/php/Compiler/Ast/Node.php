<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

abstract class Node
{
    private NodeEnvironmentInterface $env;
    private ?SourceLocation $startSourceLocation;

    public function __construct(NodeEnvironmentInterface $env, ?SourceLocation $startSourceLocation = null)
    {
        $this->env = $env;
        $this->startSourceLocation = $startSourceLocation;
    }

    public function getEnv(): NodeEnvironmentInterface
    {
        return $this->env;
    }

    public function getStartSourceLocation(): ?SourceLocation
    {
        return $this->startSourceLocation;
    }
}
