<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

abstract class Node
{
    private NodeEnvironment $env;
    private ?SourceLocation $startSourceLocation;

    public function __construct(NodeEnvironment $env, ?SourceLocation $startSourceLocation = null)
    {
        $this->env = $env;
        $this->startSourceLocation = $startSourceLocation;
    }

    public function getEnv(): NodeEnvironment
    {
        return $this->env;
    }

    public function getStartSourceLocation(): ?SourceLocation
    {
        return $this->startSourceLocation;
    }
}
