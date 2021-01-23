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
use Phel\Printer\Printer;
use Throwable;

final class ReplCommand
{
    public const COMMAND_NAME = 'repl';

    private const ENABLE_BRACKETED_PASTE = "\e[?2004h";
    private const DISABLE_BRACKETED_PASTE = "\e[?2004l";
    private const PROMPT = '>>> ';

    private ReplCommandIoInterface $io;
    private EvalCompilerInterface $compiler;
    private ExceptionPrinterInterface $exceptionPrinter;
    private ColorStyleInterface $style;

    public function __construct(
        ReplCommandIoInterface $io,
        EvalCompilerInterface $compiler,
        ExceptionPrinterInterface $exceptionPrinter,
        ColorStyleInterface $style
    ) {
        $this->io = $io;
        $this->compiler = $compiler;
        $this->exceptionPrinter = $exceptionPrinter;
        $this->style = $style;
    }

    public function run(): void
    {
        $this->io->readHistory();
        $this->io->output($this->style->yellow("Welcome to the Phel Repl\n"));
        $this->io->output('Type "exit" or press Ctrl-D to exit.' . "\n");

        $this->loopReadLineAndAnalyze();
    }

    /**
     * @throws ExitException
     */
    private function loopReadLineAndAnalyze(): void
    {
        while (true) {
            try {
                if ($this->io->isBracketedPasteSupported()) {
                    $this->io->output(self::ENABLE_BRACKETED_PASTE);
                }
                $input = $this->io->readline(self::PROMPT);
                if ($this->io->isBracketedPasteSupported()) {
                    $this->io->output(self::DISABLE_BRACKETED_PASTE);
                }

                try {
                    $this->checkInputAndAnalyze($input);
                } catch (ExitException $e) {
                    break;
                }
            } catch (Throwable $e) {
                $this->io->output($e->getTraceAsString() . PHP_EOL);
            }
        }

        $this->io->output($this->style->yellow("Bye!\n"));
    }

    /**
     * @throws ExitException
     */
    private function checkInputAndAnalyze(?string $input): void
    {
        if (null === $input || 'exit' === $input) {
            throw new ExitException();
        }

        if ('' === $input) {
            return;
        }

        $this->io->addHistory($input);

        try {
            $this->analyzeInput($input);
        } catch (ParserException|ReaderException $e) {
            $this->io->output($this->exceptionPrinter->getExceptionString($e, $e->getCodeSnippet()));
        } catch (Throwable $e) {
            $this->io->output($this->exceptionPrinter->getStackTraceString($e));
        }
    }

    /**
     * @throws ReaderException
     */
    private function analyzeInput(string $input): void
    {
        try {
            $result = $this->compiler->eval($input);
            $this->io->output(Printer::nonReadable()->print($result));
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
