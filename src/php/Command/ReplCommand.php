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
    private const INITIAL_PROMPT = 'phel(%d)> ';
    private const OPEN_PROMPT = '....(%d)> ';
    private const EXIT_REPL = 'exit';

    private ReplCommandIoInterface $io;
    private EvalCompilerInterface $compiler;
    private ExceptionPrinterInterface $exceptionPrinter;
    private ColorStyleInterface $style;
    private PrinterInterface $printer;

    /** @var string[] */
    private array $inputBuffer = [];
    private int $statementNumber = 1;

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
        $this->io->output($this->style->yellow('Welcome to the Phel Repl' . PHP_EOL));
        $this->io->output('Type "exit" or press Ctrl-D to exit.' . PHP_EOL);

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
                $this->io->output($this->style->red($e->getMessage() . PHP_EOL));
                $this->io->output($e->getTraceAsString() . PHP_EOL);
            }
        }

        $this->io->output($this->style->yellow('Bye!' . PHP_EOL));
    }

    private function addLineFromPromptToBuffer(): void
    {
        if ($this->io->isBracketedPasteSupported()) {
            $this->io->output(self::ENABLE_BRACKETED_PASTE);
        }

        $isInitialInput = empty($this->inputBuffer);
        $prompt = $isInitialInput ? self::INITIAL_PROMPT : self::OPEN_PROMPT;
        $input = $this->io->readline(sprintf($prompt, $this->statementNumber));

        if ($this->io->isBracketedPasteSupported()) {
            $this->io->output(self::DISABLE_BRACKETED_PASTE);
        }

        if ($input === null && $isInitialInput) {
            // Ctrl+D will exit the repl
            $this->inputBuffer[] = self::EXIT_REPL;
        } elseif ($input === null && !$isInitialInput) {
            // Ctrl+D will empty the buffer
            $this->inputBuffer = [];
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
            $this->io->output(PHP_EOL);
            return;
        }

        if ('' === end($this->inputBuffer)) {
            array_pop($this->inputBuffer);
            return;
        }

        $fullInput = implode(PHP_EOL, $this->inputBuffer);

        try {
            $result = $this->compiler->eval($fullInput);
            $this->io->output($this->printer->print($result) . PHP_EOL);
            $this->io->addHistory($fullInput);
            $this->inputBuffer = [];
            $this->statementNumber++;
        } catch (UnfinishedParserException $e) {
            // The input is valid but more input is missing to finish the parsing.
        } catch (CompilerException $e) {
            $this->io->output(
                $this->exceptionPrinter->getExceptionString(
                    $e->getNestedException(),
                    $e->getCodeSnippet()
                )
            );
            $this->inputBuffer = [];
        } catch (Throwable $e) {
            $this->io->output($this->exceptionPrinter->getStackTraceString($e));
            $this->inputBuffer = [];
        }
    }
}
