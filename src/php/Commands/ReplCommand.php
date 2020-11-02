<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Repl\ColorStyle;
use Phel\Commands\Repl\SystemInterface;
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

    private SystemInterface $system;
    private EvalCompiler $evalCompiler;
    private ColorStyle $style;
    private TextExceptionPrinter $exceptionPrinter;

    public static function create(
        GlobalEnvironment $globalEnv,
        SystemInterface $system
    ): self {
        Runtime::initialize($globalEnv)->loadNs("phel\core");

        return new self(
            $system,
            new EvalCompiler($globalEnv),
            ColorStyle::withStyles(),
            TextExceptionPrinter::readableWithStyle()
        );
    }

    private function __construct(
        SystemInterface $system,
        EvalCompiler $evalCompiler,
        ColorStyle $style,
        TextExceptionPrinter $exceptionPrinter
    ) {
        $this->system = $system;
        $this->evalCompiler = $evalCompiler;
        $this->style = $style;
        $this->exceptionPrinter = $exceptionPrinter;
    }

    public function run(): void
    {
        $this->system->readHistory();
        $this->system->output($this->style->yellow("Welcome to the Phel Repl\n"));
        $this->system->output('Type "exit" or press Ctrl-D to exit.' . "\n");

        while (true) {
            $this->system->output("\e[?2004h"); // Enable bracketed paste
            $input = $this->system->readline('>>> ');
            $this->system->output("\e[?2004l"); // Disable bracketed paste
            $this->readInput($input);
        }
    }

    private function readInput(?string $input): void
    {
        if (null === $input) {
            $this->system->output($this->style->yellow("Bye from Ctrl-D!\n"));
            exit;
        }

        if ('exit' === $input) {
            $this->system->output($this->style->yellow("Bye!\n"));
            exit;
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
