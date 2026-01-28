<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Event;

/**
 * Event dispatched when a Phel file has been successfully compiled.
 */
final readonly class FileCompiledEvent extends AbstractDomainEvent
{
    public function __construct(
        private string $sourceFile,
        private string $targetFile,
        private string $namespace,
    ) {
        parent::__construct();
    }

    public function sourceFile(): string
    {
        return $this->sourceFile;
    }

    public function targetFile(): string
    {
        return $this->targetFile;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }
}
