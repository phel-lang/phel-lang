<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Shared\CommandIoInterface;
use Phel\Formatter\FormatterInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FormatCommand
{
    public const COMMAND_NAME = 'fmt';

    private const PHEL_EXTENSION = 'phel';

    private FormatterInterface $formatter;
    private CommandIoInterface $io;

    /** @var array<string, bool> */
    private array $alreadyTriedToFormatFilePath = [];

    /** @var list<string> */
    private array $successfulFormattedFilePaths = [];

    public function __construct(
        FormatterInterface $formatter,
        CommandIoInterface $io
    ) {
        $this->formatter = $formatter;
        $this->io = $io;
    }

    /**
     * @param list<string> $paths
     */
    public function run(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            $this->runInPath($path);
        }

        $this->printResult();
    }

    private function runInPath(string $path): void
    {
        if (is_dir($path)) {
            $this->runFormatterInDirectory($path);
            return;
        }

        if (isset($this->alreadyTriedToFormatFilePath[$path])) {
            return;
        }

        $wasFormatted = $this->formatter->formatFile($path);

        if ($wasFormatted) {
            $this->successfulFormattedFilePaths[] = $path;
        }

        $this->alreadyTriedToFormatFilePath[$path] = true;
    }

    private function runFormatterInDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileOrPath */
            if ($fileInfo->getExtension() === self::PHEL_EXTENSION) {
                $this->runInPath($fileInfo->getPathname());
            }
        }
    }

    private function printResult(): void
    {
        if (empty($this->successfulFormattedFilePaths)) {
            $this->io->output('No files were formatted.' . PHP_EOL);
        } else {
            $this->io->output('Formatted files:' . PHP_EOL);

            foreach ($this->successfulFormattedFilePaths as $k => $filePath) {
                $this->io->output(sprintf('  %d) %s %s', $k + 1, $filePath, PHP_EOL));
            }
        }
    }
}
