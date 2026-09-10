<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use DateTimeImmutable;
use DateTimeInterface;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Shared\PhelProjectDirectory;

use function dirname;
use function is_file;
use function preg_replace;
use function sprintf;

/**
 * @internal
 */
final readonly class ErrorLog implements ErrorLogInterface
{
    private const int DEFAULT_MAX_BYTES = 1_048_576;

    private const string PREVIOUS_GENERATION_SUFFIX = '.1';

    /** Everything `ColorStyle` and the printers emit is an SGR sequence. */
    private const string SGR_ESCAPE_PATTERN = '/\e\[[0-9;]*m/';

    public function __construct(
        private string $filepath,
        private string $commandLine,
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {}

    public function writeln(string $text): void
    {
        $this->ensureParentDirectory();
        $this->rotateWhenFull();

        // Best-effort: the error already reaches stderr; a read-only log
        // location must not turn error reporting itself into a warning.
        @file_put_contents(
            $this->filepath,
            $this->entryHeader() . PHP_EOL . $this->stripTerminalEscapes($text) . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }

    private function entryHeader(): string
    {
        return sprintf(
            '[%s] %s',
            new DateTimeImmutable()->format(DateTimeInterface::ATOM),
            $this->commandLine,
        );
    }

    private function stripTerminalEscapes(string $text): string
    {
        return preg_replace(self::SGR_ESCAPE_PATTERN, '', $text) ?? $text;
    }

    /**
     * Rotates before the append, so the freshest trace always sits in the file
     * the collapsed-trace marker names.
     */
    private function rotateWhenFull(): void
    {
        if (!is_file($this->filepath)) {
            return;
        }

        $size = @filesize($this->filepath);
        if ($size === false || $size < $this->maxBytes) {
            return;
        }

        // rename() refuses to replace an existing target on Windows.
        @unlink($this->filepath . self::PREVIOUS_GENERATION_SUFFIX);
        @rename($this->filepath, $this->filepath . self::PREVIOUS_GENERATION_SUFFIX);
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
