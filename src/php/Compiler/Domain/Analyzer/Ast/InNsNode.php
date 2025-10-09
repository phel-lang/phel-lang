<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\SourceLocation;

final class InNsNode extends AbstractNode
{
    public function __construct(
        private readonly string $namespace,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct(NodeEnvironment::empty(), $sourceLocation);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
