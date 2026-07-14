<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Phel\Command\Domain\ErrorLogInterface;
use Phel\Shared\PhelProjectDirectory;

use function dirname;

final readonly class ErrorLog implements ErrorLogInterface
{
    public function __construct(
        private string $filepath,
    ) {}

    public function writeln(string $text): void
    {
        $this->ensureParentDirectory();

        // Best-effort: the error already reaches stderr; a read-only log
        // location must not turn error reporting itself into a warning.
        @file_put_contents(
            $this->filepath,
            $text . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }

    private function ensureParentDirectory(): void
    {
        $dir = dirname($this->filepath);
        // Route logs landing inside `<projectRoot>/.phel/` through the
        // shared helper so the auto `.gitignore` is seeded too.
        if (basename($dir) === PhelProjectDirectory::DIRECTORY_NAME) {
            PhelProjectDirectory::ensure(dirname($dir));
            return;
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }
    }
}
