<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Event;

use function count;

/**
 * Event dispatched when a build process completes.
 */
final readonly class BuildCompletedEvent extends AbstractDomainEvent
{
    /**
     * @param list<string> $compiledFiles
     */
    public function __construct(
        private int $totalFiles,
        private array $compiledFiles,
        private int $cachedFiles,
    ) {
        parent::__construct();
    }

    public function totalFiles(): int
    {
        return $this->totalFiles;
    }

    /**
     * @return list<string>
     */
    public function compiledFiles(): array
    {
        return $this->compiledFiles;
    }

    public function cachedFiles(): int
    {
        return $this->cachedFiles;
    }

    public function filesCompiled(): int
    {
        return count($this->compiledFiles);
    }
}
