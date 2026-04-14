<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadPathResolution;
use Phel\Lang\SourceLocation;

final class LoadNode extends AbstractNode
{
    public function __construct(
        private readonly LoadPathResolution $resolution,
        private readonly string $callerNamespace,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct(NodeEnvironment::empty(), $sourceLocation);
    }

    public function getResolution(): LoadPathResolution
    {
        return $this->resolution;
    }

    public function getCallerNamespace(): string
    {
        return $this->callerNamespace;
    }
}
