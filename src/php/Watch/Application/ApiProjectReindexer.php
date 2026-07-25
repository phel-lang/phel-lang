<?php

declare(strict_types=1);

namespace Phel\Watch\Application;

use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Watch\Domain\ProjectReindexerInterface;
use Throwable;

final readonly class ApiProjectReindexer implements ProjectReindexerInterface
{
    public function __construct(
        private ApiFacadeInterface $apiFacade,
    ) {}

    public function reindex(array $srcDirs): void
    {
        try {
            $this->apiFacade->indexProject($srcDirs);
        } catch (Throwable) {
            // Reindexing is best-effort.
        }
    }
}
