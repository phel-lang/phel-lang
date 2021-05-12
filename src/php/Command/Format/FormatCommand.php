<?php

declare(strict_types=1);

namespace Phel\Command\Format;

use Phel\Command\Shared\CommandIoInterface;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Formatter\Exceptions\ZipperException;
use Phel\Formatter\FormatterFacadeInterface;
use Throwable;

final class FormatCommand
{
    public const COMMAND_NAME = 'format';

    private CommandIoInterface $io;
    private FormatterFacadeInterface $formatterFacade;
    private PathFilterInterface $pathFilter;

    /** @var list<string> */
    private array $successfulFormattedFilePaths = [];

    public function __construct(
        CommandIoInterface $io,
        FormatterFacadeInterface $formatterFacade,
        PathFilterInterface $pathFilter
    ) {
        $this->io = $io;
        $this->formatterFacade = $formatterFacade;
        $this->pathFilter = $pathFilter;
    }

    /**
     * @param list<string> $paths
     */
    public function run(array $paths): void
    {
        foreach ($this->pathFilter->filterPaths($paths) as $path) {
            try {
                $wasFormatted = $this->formatFile($path);
                if ($wasFormatted) {
                    $this->successfulFormattedFilePaths[] = $path;
                }
            } catch (AbstractParserException $e) {
                $this->io->writeLocatedException($e, $e->getCodeSnippet());
            } catch (Throwable $e) {
                $this->io->writeStackTrace($e);
            }
        }

        $this->printResult();
    }

    /**
     * @throws AbstractParserException
     * @throws LexerValueException
     * @throws ZipperException
     *
     * @return bool True if the file was formatted. False if the file wasn't altered because it was already formatted.
     */
    public function formatFile(string $filename): bool
    {
        $code = $this->io->fileGetContents($filename);
        $formattedCode = $this->formatterFacade->format($code, $filename);
        $this->io->filePutContents($filename, $formattedCode);

        return (bool)strcmp($formattedCode, $code);
    }

    private function printResult(): void
    {
        if (empty($this->successfulFormattedFilePaths)) {
            $this->io->writeln('No files were formatted.');
        } else {
            $this->io->writeln('Formatted files:');

            foreach ($this->successfulFormattedFilePaths as $k => $filePath) {
                $this->io->writeln(sprintf('  %d) %s', $k + 1, $filePath));
            }
        }
    }
}
