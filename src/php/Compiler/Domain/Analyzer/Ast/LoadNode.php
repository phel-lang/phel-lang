<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\SourceLocation;

final class LoadNode extends AbstractNode
{
    public function __construct(
        private readonly string $filePath,
        private readonly string $callerNamespace,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct(NodeEnvironment::empty(), $sourceLocation);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getCallerNamespace(): string
    {
        return $this->callerNamespace;
    }
}
