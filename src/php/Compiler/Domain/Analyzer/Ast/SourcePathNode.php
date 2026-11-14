<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

/**
 * @internal
 */
final class SourcePathNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly ?string $value,
        private readonly bool $directory,
        ?SourceLocation $sourceLocation,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function isDirectory(): bool
    {
        return $this->directory;
    }
}
