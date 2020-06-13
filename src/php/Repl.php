<?php

declare(strict_types=1);

namespace Phel;

use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\ReaderException;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\Repl\Readline;
use Throwable;

final class Repl
{
    private Readline $readline;

    public function __construct($workingDir)
    {
        $this->readline = new Readline($workingDir . '.phel-repl-history');
    }

    public function run(): void
    {
        $this->readline->readHistory();

        $globalEnv = new GlobalEnvironment();
        $rt = Runtime::initialize($globalEnv);
        $rt->loadNs("phel\core");

        $reader = new Reader();
        $lexer = new Lexer();
        $analyzer = new Analyzer($globalEnv);
        $emitter = new Emitter();
        $printer = new Printer();
        $exceptionPrinter = new TextExceptionPrinter();

        $this->output($this->color("Welcome to the Phel Repl\n", 'yellow'));
        $this->output('Type "quit" or press Ctrl-D to exit.' . "\n");
        while (true) {
            $this->output("\e[?2004h"); // Enable bracketed paste
            $input = $this->readline->readline('>>> ');
            $this->output("\e[?2004l"); // Disable bracketed paste

            if ($input === false) {
                $this->output($this->color("Bye from Ctrl+D!\n", 'yellow'));
                exit;
            }

            if ($input === 'quit') {
                $this->output($this->color("Bye!\n", 'yellow'));
                exit;
            }

            if ($input === '') {
                continue;
            }

            $this->readline->addHistory($input);

            try {
                $tokenStream = $lexer->lexString($input);
                $readerResult = $reader->readNext($tokenStream);

                if (!$readerResult) {
                    $this->output("Nothing to evaluate.\n");
                    continue;
                }

                try {
                    $node = $analyzer->analyze(
                        $readerResult->getAst(),
                        NodeEnvironment::empty()->withContext(NodeEnvironment::CTX_RET)
                    );
                    $code = $emitter->emitAsString($node);
                    $result = $emitter->eval($code);

                    $this->output($printer->print($result, false));
                    $this->output(PHP_EOL);
                } catch (AnalyzerException $e) {
                    $exceptionPrinter->printException($e, $readerResult->getCodeSnippet());
                }
            } catch (ReaderException $e) {
                $exceptionPrinter->printException($e, $e->getCodeSnippet());
            } catch (Throwable $e) {
                $exceptionPrinter->printStackTrace($e);
            }
        }
    }

    private function color($text = '', $color = null): string
    {
        $styles = [
            'green'  => "\033[0;32m%s\033[0m",
            'red'    => "\033[31;31m%s\033[0m",
            'yellow' => "\033[33;33m%s\033[0m",
            'blue'   => "\033[33;34m%s\033[0m",
        ];

        return sprintf($styles[$color] ?? "%s", $text);
    }

    private function output($value): void
    {
        fwrite(STDOUT, $value);
    }
}
