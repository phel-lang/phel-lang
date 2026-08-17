<?php

declare(strict_types=1);

namespace Phel\Formatter\Application;

use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Formatter\Domain\Exception\FilePathException;
use Phel\Formatter\Domain\ExcludePatterns;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Formatter\Domain\IO\ValidatedFileIoInterface;
use Phel\Formatter\Domain\PathFilterInterface;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Formatter\FormatResult;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final readonly class PathsFormatter
{
    /**
     * @param CommandFacadeInterface   $commandFacade Writes located exceptions and
     *                                                stack traces to the CLI output
     * @param FormatterInterface       $formatter     Formats a single file's source
     * @param PathFilterInterface      $pathFilter    Expands the input paths into the
     *                                                concrete `.phel` files to format
     * @param ValidatedFileIoInterface $fileIo        Reads and writes file contents
     */
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private FormatterInterface $formatter,
        private PathFilterInterface $pathFilter,
        private ValidatedFileIoInterface $fileIo,
    ) {}

    /**
     * Only the failures {@see self::formatFile()} documents as coming from the
     * file itself (bad source, missing path) are reported and skipped, so one
     * unformattable file does not abort the batch. Anything else, an I/O
     * failure or a bug in a rule, propagates: swallowing it would report a
     * successful run that silently skipped files.
     *
     * The skipped paths are still collected in
     * {@see FormatResult::failedPaths()} so callers can exit non-zero; a batch
     * that only printed the errors and returned success made `phel format
     * --dry-run` usable as a green CI gate over unparsable sources.
     *
     * A path matching one of `$exclude` (see {@see ExcludePatterns}) is
     * skipped before it is read and lands in neither bucket; `-v` names it.
     *
     * @param list<string> $paths
     * @param list<string> $exclude
     */
    public function format(array $paths, OutputInterface $output, bool $dryRun = false, array $exclude = []): FormatResult
    {
        $formattedFilePaths = [];
        $failedFilePaths = [];
        $excluded = ExcludePatterns::fromWorkingDirectory($exclude);

        foreach ($this->pathFilter->filterPaths($paths) as $path) {
            if ($excluded->matches($path)) {
                if ($output->isVerbose()) {
                    $output->writeln('Excluded: ' . $path);
                }

                continue;
            }

            try {
                $wasFormatted = $this->formatFile($path, $dryRun);
                if ($wasFormatted) {
                    $formattedFilePaths[] = $path;
                }
            } catch (AbstractParserException $e) {
                $this->commandFacade->writeLocatedException($output, $e, $e->getCodeSnippet());
                $failedFilePaths[] = $path;
            } catch (FilePathException|LexerValueException|ZipperException $e) {
                $this->commandFacade->writeStackTrace($output, $e);
                $failedFilePaths[] = $path;
            }
        }

        return new FormatResult($formattedFilePaths, $failedFilePaths);
    }

    /**
     * @throws FilePathException
     * @throws LexerValueException
     * @throws ZipperException
     * @throws AbstractParserException
     *
     * @return bool True when the file's contents differ from the formatted output.
     *              Under $dryRun the file is left untouched.
     */
    private function formatFile(string $filename, bool $dryRun): bool
    {
        $this->fileIo->checkIfValid($filename);

        $code = $this->fileIo->getContents($filename);
        $formattedCode = $this->formatter->format($code, $filename);
        $changed = (bool) strcmp($formattedCode, $code);

        if ($changed && !$dryRun) {
            $this->fileIo->putContents($filename, $formattedCode);
        }

        return $changed;
    }
}
