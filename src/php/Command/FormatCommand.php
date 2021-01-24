<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Shared\CommandIoInterface;
use Phel\Formatter\FormatterInterface;

final class FormatCommand
{
    public const COMMAND_NAME = 'fmt';

    private const PHEL_EXTENSION = 'phel';

    private FormatterInterface $formatter;
    private CommandIoInterface $io;

    /** @var string[] */
    private array $filePaths = [];

    public function __construct(
        FormatterInterface $formatter,
        CommandIoInterface $io
    ) {
        $this->formatter = $formatter;
        $this->io = $io;
    }

    public function run(array $paths): void
    {
        foreach ($paths as $fileOrPath) {
            $this->runFileOrPath($fileOrPath);
        }

        $this->printResult();
    }

    private function runFileOrPath(string $fileOrPath): void
    {
        if (is_dir($fileOrPath)) {
            $this->runFormatterInDirectory($fileOrPath);
        } elseif ($this->hasPhelExtension($fileOrPath)) {
            $this->formatter->formatFile($fileOrPath);
            $this->filePaths[] = $fileOrPath;
        }
    }

    private function runFormatterInDirectory(string $directory): void
    {
        $paths = array_diff(scandir($directory), ['..', '.']);

        foreach ($paths as $fileOrPath) {
            $this->runFileOrPath("$directory/$fileOrPath");
        }
    }

    private function hasPhelExtension(string $fileOrPath): bool
    {
        return self::PHEL_EXTENSION === pathinfo($fileOrPath, PATHINFO_EXTENSION);
    }

    private function printResult(): void
    {
        $this->io->output('Formatted files:' . PHP_EOL);

        foreach ($this->filePaths as $filePath) {
            $this->io->output('# ' . $filePath . PHP_EOL);
        }
    }
}
