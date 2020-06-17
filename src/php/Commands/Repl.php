<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Analyzer;
use Phel\Commands\Repl\ColorStyle;
use Phel\Commands\Repl\Readline;
use Phel\Emitter;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\ReaderException;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\GlobalEnvironment;
use Phel\Lexer;
use Phel\NodeEnvironment;
use Phel\Printer;
use Phel\Reader;
use Phel\Runtime;
use Throwable;

final class Repl
{
    private Readline $readline;
    private Reader $reader;
    private Lexer $lexer;
    private Analyzer $analyzer;
    private Emitter $emitter;
    private ColorStyle $style;
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
            $this->output($this->style->yellow("Bye from Ctrl+D!\n"));
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

            $this->output(Printer::nonReadable()->print($result));
            $this->output(PHP_EOL);
        } catch (AnalyzerException $e) {
            $this->exceptionPrinter->printException($e, $readerResult->getCodeSnippet());
        }
    }
}
