<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Repl\ColorStyleInterface;
use Phel\Command\Repl\ReplCommandIoInterface;
use Phel\Compiler\EvalCompilerInterface;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ExceptionPrinterInterface;
use Phel\Exceptions\ExitException;
use Phel\Exceptions\ParserException;
use Phel\Exceptions\ReaderException;
use Phel\Exceptions\WrongNumberOfParenthesisException;
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

    private int $statementNumber = 1;
    private string $inputBuffer = '';

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
                $this->inputBuffer = '';
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

        $prompt = empty($this->inputBuffer) ? self::INITIAL_PROMPT : self::OPEN_PROMPT;
        $input = $this->io->readline($prompt);

        if ($this->io->isBracketedPasteSupported()) {
            $this->io->output(self::DISABLE_BRACKETED_PASTE);
        }

        if ($input === null) {
            $input = self::EXIT_REPL;
        }

        $this->inputBuffer .= $input;
    }

    /**
     * @throws ExitException
     */
    private function checkExitInputBuffer(): void
    {
        if (self::EXIT_REPL === $this->inputBuffer) {
            throw new ExitException();
        }
    }

    /**
     * @throws WrongNumberOfParenthesisException
     */
    private function analyzeInputBuffer(): void
    {
        if ('' === $this->inputBuffer) {
            return;
        }

        if (!$this->isInputReadyToBeAnalyzed($this->inputBuffer)) {
            return;
        }

        $this->io->addHistory($this->inputBuffer);

        try {
            $this->analyzeInput($this->inputBuffer);
        } catch (ParserException|ReaderException $e) {
            $this->io->output($this->exceptionPrinter->getExceptionString($e, $e->getCodeSnippet()));
        } catch (Throwable $e) {
            $this->io->output($this->exceptionPrinter->getStackTraceString($e));
        }

        $this->inputBuffer = '';
    }

    /**
     * @throws WrongNumberOfParenthesisException
     */
    private function isInputReadyToBeAnalyzed(string $input): bool
    {
        $totalOpenParenthesis = substr_count($input, '(');
        $totalCloseParenthesis = substr_count($input, ')');

        if ($totalCloseParenthesis > $totalOpenParenthesis) {
            throw new WrongNumberOfParenthesisException();
        }

        return $totalCloseParenthesis === $totalOpenParenthesis;
    }

    /**
     * @throws ReaderException
     */
    private function analyzeInput(string $input): void
    {
        try {
            $result = $this->compiler->eval($input);
            $this->io->output($this->printer->print($result));
            $this->io->output(PHP_EOL);
            $this->statementNumber++;
        } catch (CompilerException $e) {
            $this->io->output(
                $this->exceptionPrinter->getExceptionString(
                    $e->getNestedException(),
                    $e->getCodeSnippet()
                )
            );
        }
    }
}
