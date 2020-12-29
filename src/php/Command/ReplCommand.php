<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Repl\ColorStyle;
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

    private ReplCommandIoInterface $io;
    private EvalCompilerInterface $compiler;
    private ExceptionPrinterInterface $exceptionPrinter;
    private ColorStyle $style;

    public function __construct(
        ReplCommandIoInterface $io,
        EvalCompilerInterface $compiler,
        ExceptionPrinterInterface $exceptionPrinter,
        ColorStyle $style
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

        try {
            $this->loopReadLineAndAnalyze();
        } catch (ExitException $e) {
            $this->io->output($e->getMessage());
        }
    }

    /**
     * @throws ExitException
     */
    private function loopReadLineAndAnalyze(): void
    {
        while (true) {
            $this->io->output("\e[?2004h"); // Enable bracketed paste
            $input = $this->io->readline('>>> ');
            $this->io->output("\e[?2004l"); // Disable bracketed paste
            $this->checkInputAndAnalyze($input);
        }
    }

    /**
     * @throws ExitException
     */
    private function checkInputAndAnalyze(?string $input): void
    {
        if (null === $input) {
            throw new ExitException($this->style->yellow("Bye from Ctrl-D!\n"));
        }

        if ('exit' === $input) {
            throw new ExitException($this->style->yellow("Bye!\n"));
        }

        if ('' === $input) {
            return;
        }

        $this->io->addHistory($input);

        try {
            $this->analyzeInput($input);
        } catch (ParserException $e) {
            $this->exceptionPrinter->printException($e, $e->getCodeSnippet());
        } catch (ReaderException $e) {
            $this->exceptionPrinter->printException($e, $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->exceptionPrinter->printStackTrace($e);
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
            $this->exceptionPrinter->printException(
                $e->getNestedException(),
                $e->getCodeSnippet()
            );
        }
    }
}
