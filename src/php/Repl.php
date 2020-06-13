<?php

declare(strict_types=1);

namespace Phel;

use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\ReaderException;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\Repl\Color;
use Phel\Repl\Readline;
use Throwable;

final class Repl
{
    private Readline $readline;
    private Reader $reader;
    private Lexer $lexer;
    private Analyzer $analyzer;
    private Emitter $emitter;
    private Printer $printer;
    private Color $color;
    private TextExceptionPrinter $exceptionPrinter;

    public function __construct($workingDir)
    {
        $this->readline = new Readline($workingDir . '.phel-repl-history');

        $globalEnv = new GlobalEnvironment();
        Runtime::initialize($globalEnv)->loadNs("phel\core");

        $this->reader = new Reader();
        $this->lexer = new Lexer();
        $this->analyzer = new Analyzer($globalEnv);
        $this->emitter = new Emitter();
        $this->printer = new Printer();
        $this->color = Color::withDefaultStyles();
        $this->exceptionPrinter = new TextExceptionPrinter(Color::withDefaultStyles());
    }

    public function run(): void
    {
        $this->readline->readHistory();
        $this->output($this->color->prompt("Welcome to the Phel Repl\n", 'yellow'));
        $this->output('Type "exit" or press Ctrl-D to exit.' . "\n");

        while (true) {
            $this->output("\e[?2004h"); // Enable bracketed paste
            $input = $this->readline->readline('>>> ');
            $this->output("\e[?2004l"); // Disable bracketed paste
            $this->readInput($input);
        }
    }

    private function output($value): void
    {
        fwrite(STDOUT, $value);
    }

    /**
     * @param false|string $input
     */
    private function readInput($input): void
    {
        if (false === $input) {
            $this->output($this->color->prompt("Bye from Ctrl+D!\n", 'yellow'));
            exit;
        }

        if ('exit' === $input) {
            $this->output($this->color->prompt("Bye!\n", 'yellow'));
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
        $tokenStream = $this->lexer->lexString($input);
        $readerResult = $this->reader->readNext($tokenStream);

        if (!$readerResult) {
            $this->output("Nothing to evaluate.\n");
            return;
        }

        try {
            $node = $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()->withContext(NodeEnvironment::CTX_RET)
            );
            $code = $this->emitter->emitAsString($node);
            $result = $this->emitter->eval($code);

            $this->output($this->printer->print($result, false));
            $this->output(PHP_EOL);
        } catch (AnalyzerException $e) {
            $this->exceptionPrinter->printException($e, $readerResult->getCodeSnippet());
        }
    }
}
