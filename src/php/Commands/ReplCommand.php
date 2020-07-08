<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Repl\ColorStyle;
use Phel\Commands\Repl\Readline;
use Phel\Compiler;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\GlobalEnvironment;
use Phel\Printer;
use Phel\Runtime;
use Throwable;

final class ReplCommand
{
    public const NAME = 'repl';

    private Readline $readline;
    private Compiler $compiler;
    private ColorStyle $style;
    private TextExceptionPrinter $exceptionPrinter;

    public function __construct(string $workingDir)
    {
        $this->readline = new Readline($workingDir . '.phel-repl-history');

        $globalEnv = new GlobalEnvironment();
        Runtime::initialize($globalEnv)->loadNs("phel\core");

        $this->compiler = new Compiler($globalEnv);
        $this->style = ColorStyle::withStyles();
        $this->exceptionPrinter = TextExceptionPrinter::readableWithStyle();
    }

    public function run(): void
    {
        $this->readline->readHistory();
        $this->output($this->style->yellow("Welcome to the Phel Repl\n"));
        $this->output('Type "exit" or press Ctrl-D to exit.' . "\n");

        while (true) {
            $this->output("\e[?2004h"); // Enable bracketed paste
            $input = $this->readline->readline('>>> ');
            $this->output("\e[?2004l"); // Disable bracketed paste
            $this->readInput($input);
        }
    }

    private function output(string $value): void
    {
        fwrite(STDOUT, $value);
    }

    private function readInput(?string $input): void
    {
        if (null === $input) {
            $this->output($this->style->yellow("Bye from Ctrl-D!\n"));
            exit;
        }

        if ('exit' === $input) {
            $this->output($this->style->yellow("Bye!\n"));
            exit;
        }

        if ('' === $input) {
            return;
        }

        $this->readline->addHistory($input);

        try {
            $this->analyzeInput($input);
        } catch (ReaderException $e) {
            $this->exceptionPrinter->printException($e, $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->exceptionPrinter->printStackTrace($e);
        }
    }

    private function analyzeInput(string $input): void
    {
        try {
            $result = $this->compiler->eval($input);
            $this->output(Printer::nonReadable()->print($result));
            $this->output(PHP_EOL);
        } catch (CompilerException $e) {
            $this->exceptionPrinter->printException(
                $e->getNestedException(),
                $e->getCodeSnippet()
            );
        }
    }
}
