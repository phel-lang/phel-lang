<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

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
