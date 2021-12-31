<?php

declare(strict_types=1);

namespace Phel\Formatter\Command;

use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Formatter\Domain\PathFilterInterface;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class FormatCommand extends Command
{
    public const COMMAND_NAME = 'format';

    private CommandFacadeInterface $commandFacade;
    private FormatterInterface $formatter;
    private PathFilterInterface $pathFilter;

    /** @var list<string> */
    private array $successfulFormattedFilePaths = [];

    public function __construct(
        CommandFacadeInterface $commandFacade,
        FormatterInterface $formatter,
        PathFilterInterface $pathFilter
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->commandFacade = $commandFacade;
        $this->formatter = $formatter;
        $this->pathFilter = $pathFilter;
    }

    /**
     * @internal Public method for test purposes
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    protected function configure(): void
    {
        $this->setDescription('Formats the given files.')
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY|InputArgument::REQUIRED,
                'The file paths that you want to format.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        foreach ($this->pathFilter->filterPaths($paths) as $path) {
            try {
                $wasFormatted = $this->formatFile($path);
                if ($wasFormatted) {
                    $this->successfulFormattedFilePaths[] = $path;
                }
            } catch (AbstractParserException $e) {
                $this->commandFacade->writeLocatedException($output, $e, $e->getCodeSnippet());
            } catch (Throwable $e) {
                $this->commandFacade->writeStackTrace($output, $e);
            }
        }

        $this->printResult($output);

        return self::SUCCESS;
    }

    /**
     * @throws AbstractParserException
     * @throws LexerValueException
     * @throws ZipperException
     *
     * @return bool True if the file was formatted. False if the file wasn't altered because it was already formatted.
     */
    private function formatFile(string $filename): bool
    {
        if (is_dir($filename)) {
            throw new RuntimeException(sprintf('"%s" is a directory but needs to be a file path', $filename));
        }

        if (!is_file($filename)) {
            throw new RuntimeException(sprintf('File path "%s" not found', $filename));
        }

        $code = file_get_contents($filename);
        $formattedCode = $this->formatter->format($code, $filename);
        file_put_contents($filename, $formattedCode);

        return (bool)strcmp($formattedCode, $code);
    }

    private function printResult(OutputInterface $output): void
    {
        if (empty($this->successfulFormattedFilePaths)) {
            $output->writeln('No files were formatted.');
        } else {
            $output->writeln('Formatted files:');

            foreach ($this->successfulFormattedFilePaths as $k => $filePath) {
                $output->writeln(sprintf('  %d) %s', $k + 1, $filePath));
            }
        }
    }
}
