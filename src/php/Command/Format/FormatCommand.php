<?php

declare(strict_types=1);

namespace Phel\Command\Format;

use Phel\Command\Shared\CommandIoInterface;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Formatter\Exceptions\ZipperException;
use Phel\Formatter\FormatterFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class FormatCommand extends Command
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
        parent::__construct(self::COMMAND_NAME);
        $this->io = $io;
        $this->formatterFacade = $formatterFacade;
        $this->pathFilter = $pathFilter;
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

    public function execute(InputInterface $input, OutputInterface $output): int
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
                $this->io->writeLocatedException($e, $e->getCodeSnippet());
            } catch (Throwable $e) {
                $this->io->writeStackTrace($e);
            }
        }

        $this->printResult();

        return self::SUCCESS;
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
