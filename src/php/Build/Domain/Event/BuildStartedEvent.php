<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Event;

/**
 * Event dispatched when a build process starts.
 */
final readonly class BuildStartedEvent extends AbstractDomainEvent
{
    /**
     * @param list<string> $sourceDirectories
     */
    public function __construct(
        private array $sourceDirectories,
        private string $outputDirectory,
    ) {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function sourceDirectories(): array
    {
        return $this->sourceDirectories;
    }

    public function outputDirectory(): string
    {
        return $this->outputDirectory;
    }
}
