<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Printer\Printer;
use Phel\Shared\Printer\PrinterInterface;
use Phel\Shared\ScalarCoercion;
use Throwable;

use function fgets;
use function fwrite;
use function implode;
use function in_array;
use function is_callable;
use function sprintf;
use function stream_isatty;
use function trim;

/**
 * Interactive blocking sub-REPL entered by code compiled from the `(break)`
 * special form. Prints the captured locals, then reads and evaluates
 * expressions with those locals in scope until the user resumes.
 */
final class BreakpointDebugger
{
    /** @var resource */
    private $input;

    /** @var resource */
    private $output;

    /**
     * True when the process's real STDIN is the input (i.e. no stream was
     * injected). Reading it is only safe on an interactive terminal: under a
     * parallel test worker, a pipe, or cron it belongs to another protocol,
     * so a `(break)` there resumes without ever touching the stream.
     */
    private readonly bool $usesRealStdin;

    /**
     * @param resource|null $input  Defaults to STDIN
     * @param resource|null $output Defaults to STDERR
     */
    public function __construct(
        private readonly CompilerFacadeInterface $compilerFacade,
        $input = null,
        $output = null,
    ) {
        $this->usesRealStdin = $input === null;
        $this->input = $input ?? STDIN;
        $this->output = $output ?? STDERR;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $locals
     */
    public function enter(PersistentMapInterface $locals): void
    {
        $printer = Printer::readable();

        // Iterate the map once so param names and argument values stay aligned.
        $names = [];
        $values = [];
        foreach ($locals as $name => $value) {
            $names[] = ScalarCoercion::toString($name);
            $values[] = $value;
        }

        if ($this->usesRealStdin && !stream_isatty($this->input)) {
            // No human attached (CI, test worker, pipe, cron): reading the
            // real STDIN would steal another consumer's input, so resume.
            $this->writeln('--- breakpoint skipped (no interactive terminal) ---');
            return;
        }

        $this->printBanner($printer, $names, $values);

        while (true) {
            $this->write('break> ');

            $line = fgets($this->input);
            if ($line === false) {
                // EOF (e.g. an injected, exhausted stream): resume.
                return;
            }

            $input = trim($line);
            if ($input === '') {
                continue;
            }

            if (in_array($input, ['(continue)', ':continue', 'continue', 'c'], true)) {
                return;
            }

            if (in_array($input, [':locals', 'l'], true)) {
                $this->printLocals($printer, $names, $values);
                continue;
            }

            $this->evalInput($printer, $names, $values, $input);
        }
    }

    /**
     * @param list<string> $names
     * @param list<mixed>  $values
     */
    private function printBanner(PrinterInterface $printer, array $names, array $values): void
    {
        $this->writeln('--- breakpoint ---');
        $this->printLocals($printer, $names, $values);
        $this->writeln('type an expression to eval it with locals in scope; (continue) to resume');
    }

    /**
     * @param list<string> $names
     * @param list<mixed>  $values
     */
    private function printLocals(PrinterInterface $printer, array $names, array $values): void
    {
        foreach ($names as $i => $name) {
            $this->writeln(sprintf('  %s = %s', $name, $printer->print($values[$i])));
        }
    }

    /**
     * @param list<string> $names
     * @param list<mixed>  $values
     */
    private function evalInput(PrinterInterface $printer, array $names, array $values, string $input): void
    {
        try {
            $code = sprintf('(fn [%s] %s)', implode(' ', $names), $input);
            $fn = $this->compilerFacade->eval($code, new CompileOptions());
            if (!is_callable($fn)) {
                $this->writeln('error: expression did not compile to a callable');
                return;
            }

            $result = $fn(...$values);
            $this->writeln(sprintf('=> %s', $printer->print($result)));
        } catch (Throwable $throwable) {
            $this->writeln(sprintf('error: %s', $throwable->getMessage()));
        }
    }

    private function write(string $text): void
    {
        fwrite($this->output, $text);
    }

    private function writeln(string $text): void
    {
        fwrite($this->output, $text . PHP_EOL);
    }
}
