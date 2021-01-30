<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Repl\ColorStyleInterface;
use Phel\Command\Repl\ReplCommandIoInterface;
use Phel\Compiler\EvalCompilerInterface;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ExceptionPrinterInterface;
use Phel\Exceptions\ExitException;
use Phel\Exceptions\Parser\UnfinishedParserException;
use Phel\Printer\PrinterInterface;
use Throwable;

final class ReplCommand
{
    public const COMMAND_NAME = 'repl';

    private const ENABLE_BRACKETED_PASTE = "\e[?2004h";
    private const DISABLE_BRACKETED_PASTE = "\e[?2004l";
    private const INITIAL_PROMPT = 'phel:%d> ';
    private const OPEN_PROMPT = '....:%d> ';
    private const EXIT_REPL = 'exit';

    private ReplCommandIoInterface $io;
    private EvalCompilerInterface $compiler;
    private ExceptionPrinterInterface $exceptionPrinter;
    private ColorStyleInterface $style;
    private PrinterInterface $printer;

    /** @var string[] */
    private array $inputBuffer = [];
    private int $lineNumber = 1;

    public function __construct(
        ReplCommandIoInterface $io,
        EvalCompilerInterface $compiler,
        ExceptionPrinterInterface $exceptionPrinter,
        ColorStyleInterface $style,
        PrinterInterface $printer
    ) {
        $this->io = $io;
        $this->compiler = $compiler;
        $this->exceptionPrinter = $exceptionPrinter;
        $this->style = $style;
        $this->printer = $printer;
    }

    public function run(): void
    {
        $this->io->readHistory();
        $this->io->writeln($this->style->yellow('Welcome to the Phel Repl'));
        $this->io->writeln('Type "exit" or press Ctrl-D to exit.');

        $this->loopReadLineAndAnalyze();
    }

    private function loopReadLineAndAnalyze(): void
    {
        while (true) {
            try {
                $this->addLineFromPromptToBuffer();
                $this->checkExitInputBuffer();
                $this->analyzeInputBuffer();
            } catch (ExitException $e) {
                break;
            } catch (Throwable $e) {
                $this->inputBuffer = [];
                $this->io->writeln($this->style->red($e->getMessage()));
                $this->io->writeln($e->getTraceAsString());
            }
        }

        $this->io->writeln($this->style->yellow('Bye!'));
    }

    private function addLineFromPromptToBuffer(): void
    {
        if ($this->io->isBracketedPasteSupported()) {
            $this->io->write(self::ENABLE_BRACKETED_PASTE);
        }

        $isInitialInput = empty($this->inputBuffer);
        $prompt = $isInitialInput ? self::INITIAL_PROMPT : self::OPEN_PROMPT;
        $input = $this->io->readline(sprintf($prompt, $this->lineNumber));

        if ($this->io->isBracketedPasteSupported()) {
            $this->io->write(self::DISABLE_BRACKETED_PASTE);
        }

        $this->lineNumber++;

        if ($input === null && $isInitialInput) {
            // Ctrl+D will exit the repl
            $this->inputBuffer[] = self::EXIT_REPL;
        } elseif ($input === null && !$isInitialInput) {
            // Ctrl+D will empty the buffer
            $this->inputBuffer = [];
            $this->io->writeln();
        } else {
            $this->inputBuffer[] = $input;
        }
    }

    /**
     * @throws ExitException
     */
    private function checkExitInputBuffer(): void
    {
        $firstInput = $this->inputBuffer[0] ?? '';

        if (self::EXIT_REPL === $firstInput) {
            throw new ExitException();
        }
    }

    private function analyzeInputBuffer(): void
    {
        if (empty($this->inputBuffer)) {
            return;
        }

        $fullInput = implode(PHP_EOL, $this->inputBuffer);

        try {
            $result = $this->compiler->eval($fullInput, $this->lineNumber - count($this->inputBuffer));
            $this->addHistory($fullInput);
            $this->io->writeln($this->printer->print($result));

            $this->inputBuffer = [];
        } catch (UnfinishedParserException $e) {
            // The input is valid but more input is missing to finish the parsing.
        } catch (CompilerException $e) {
            $this->io->write(
                $this->exceptionPrinter->getExceptionString(
                    $e->getNestedException(),
                    $e->getCodeSnippet()
                )
            );
            $this->addHistory($fullInput);
            $this->inputBuffer = [];
        } catch (Throwable $e) {
            $this->io->writeln($this->exceptionPrinter->getStackTraceString($e));
            $this->addHistory($fullInput);
            $this->inputBuffer = [];
        }
    }

    private function addHistory(string $input): void
    {
        if ('' !== $input) {
            $this->io->addHistory($input);
        }
    }
}
