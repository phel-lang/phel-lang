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
    ) {}

    public function getEnv(): NodeEnvironmentInterface
    {
        return $this->env;
    }

    /**
     * The source location of the form this node was analysed from, used
     * by error reporting and source maps to point back at the original
     * Phel. `null` for nodes the compiler synthesises without original
     * source (macro expansion, simplification, inlining) and for a few
     * resolver-built literals (e.g. `__DIR__` / `__FILE__`); analysers
     * should propagate the location from the source form wherever one
     * exists.
     */
    public function getStartSourceLocation(): ?SourceLocation
    {
        return $this->startSourceLocation;
    }
}
