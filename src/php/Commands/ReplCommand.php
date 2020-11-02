<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Repl\ColorStyle;
use Phel\Commands\Repl\PromptLineReader;
use Phel\Commands\Repl\LineReaderInterface;
use Phel\Compiler\EvalCompiler;
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

    private LineReaderInterface $lineReader;
    private EvalCompiler $evalCompiler;
    private ColorStyle $style;
    private TextExceptionPrinter $exceptionPrinter;

    public function __construct(
        GlobalEnvironment $globalEnv,
        LineReaderInterface $lineReader
    ) {
        Runtime::initialize($globalEnv)->loadNs("phel\core");

        $this->lineReader = $lineReader;
        $this->evalCompiler = new EvalCompiler($globalEnv);
        $this->style = ColorStyle::withStyles();
        $this->exceptionPrinter = TextExceptionPrinter::readableWithStyle();
    }

    public function run(): void
    {
        $this->lineReader->readHistory();
        $this->output($this->style->yellow("Welcome to the Phel Repl\n"));
        $this->output('Type "exit" or press Ctrl-D to exit.' . "\n");

        while (true) {
            $this->output("\e[?2004h"); // Enable bracketed paste
            $input = $this->lineReader->readline('>>> ');
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

        $this->lineReader->addHistory($input);

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
            $result = $this->evalCompiler->eval($input);
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
