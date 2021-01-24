<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Repl\ColorStyleInterface;
use Phel\Command\Repl\InputValidator;
use Phel\Command\Repl\ReplCommandIoInterface;
use Phel\Compiler\EvalCompilerInterface;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ExceptionPrinterInterface;
use Phel\Exceptions\ExitException;
use Phel\Exceptions\ParserException;
use Phel\Exceptions\ReaderException;
use Phel\Printer\PrinterInterface;
use Throwable;

final class ReplCommand
{
    public const COMMAND_NAME = 'repl';

    private const ENABLE_BRACKETED_PASTE = "\e[?2004h";
    private const DISABLE_BRACKETED_PASTE = "\e[?2004l";
    private const INITIAL_PROMPT = '>>> ';
    private const OPEN_PROMPT = '... ';
    private const EXIT_REPL = 'exit';

    private ReplCommandIoInterface $io;
    private EvalCompilerInterface $compiler;
    private ExceptionPrinterInterface $exceptionPrinter;
    private ColorStyleInterface $style;
    private PrinterInterface $printer;

    /** @var string[] */
    private array $inputBuffer = [];

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

        $this->inputBuffer[] = $input;
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

    /**
     * @throws \RuntimeException
     */
    private function analyzeInputBuffer(): void
    {
        if ('' === end($this->inputBuffer)) {
            return;
        }

        if (!(new InputValidator())->isInputReadyToBeAnalyzed($this->inputBuffer)) {
            return;
        }

        $inputAsString = implode(PHP_EOL, $this->inputBuffer);
        $this->io->addHistory($inputAsString);

        try {
            $this->analyzeInput($inputAsString);
        } catch (ParserException|ReaderException $e) {
            $this->io->output($this->exceptionPrinter->getExceptionString($e, $e->getCodeSnippet()));
        } catch (Throwable $e) {
            $this->io->output($this->exceptionPrinter->getStackTraceString($e));
        }

        $this->inputBuffer = [];
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
