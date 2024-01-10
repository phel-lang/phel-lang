<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

abstract class AbstractNode
{
    public function __construct(
        private readonly NodeEnvironmentInterface $env,
        private readonly ?SourceLocation $startSourceLocation = null,
    ) {
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
