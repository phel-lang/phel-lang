<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Repl\ColorStyle;
use Phel\Commands\Repl\SystemInterface;
use Phel\Compiler\EvalCompilerInterface;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ExceptionPrinter;
use Phel\Exceptions\ExitException;
use Phel\Exceptions\ReaderException;
use Phel\Printer;
use Throwable;

final class ReplCommand
{
    public const NAME = 'repl';

    private SystemInterface $system;
    private EvalCompilerInterface $evalCompiler;
    private ExceptionPrinter $exceptionPrinter;
    private ColorStyle $style;

    public function __construct(
        SystemInterface $system,
        EvalCompilerInterface $evalCompiler,
        ExceptionPrinter $exceptionPrinter,
        ColorStyle $colorStyle
    ) {
        $this->system = $system;
        $this->evalCompiler = $evalCompiler;
        $this->exceptionPrinter = $exceptionPrinter;
        $this->style = $colorStyle;
    }

    public function run(): void
    {
        $this->system->readHistory();
        $this->system->output($this->style->yellow("Welcome to the Phel Repl\n"));
        $this->system->output('Type "exit" or press Ctrl-D to exit.' . "\n");

        try {
            $this->loopReadLineAndAnalyze();
        } catch (ExitException $e) {
            $this->system->output($e->getMessage());
        }
    }

    /**
     * @throws ExitException
     */
    private function loopReadLineAndAnalyze(): void
    {
        while (true) {
            $this->system->output("\e[?2004h"); // Enable bracketed paste
            $input = $this->system->readline('>>> ');
            $this->system->output("\e[?2004l"); // Disable bracketed paste
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

        $this->system->addHistory($input);

        try {
            $this->analyzeInput($input);
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
            $result = $this->evalCompiler->eval($input);
            $this->system->output(Printer::nonReadable()->print($result));
            $this->system->output(PHP_EOL);
        } catch (CompilerException $e) {
            $this->exceptionPrinter->printException(
                $e->getNestedException(),
                $e->getCodeSnippet()
            );
        }
    }
}
