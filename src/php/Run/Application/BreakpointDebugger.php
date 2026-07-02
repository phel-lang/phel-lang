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

use function feof;
use function fgets;
use function fwrite;
use function implode;
use function in_array;
use function is_callable;
use function microtime;
use function sprintf;
use function stream_isatty;
use function stream_set_blocking;
use function trim;
use function usleep;

/**
 * Interactive blocking sub-REPL entered by code compiled from the `(break)`
 * special form. Prints the captured locals, then reads and evaluates
 * expressions with those locals in scope until the user resumes.
 */
final class BreakpointDebugger
{
    /**
     * How long to poll a non-interactive input for a line before treating the
     * silence as "resume". Bounds the wait so a `(break)` reached under CI, a
     * cron job, or any pipe with no writer can never hang the process.
     */
    private const float NON_INTERACTIVE_READ_TIMEOUT_SECONDS = 2.0;

    /** @var resource */
    private $input;

    /** @var resource */
    private $output;

    /**
     * @param resource|null $input  Defaults to STDIN
     * @param resource|null $output Defaults to STDERR
     */
    public function __construct(
        private readonly CompilerFacadeInterface $compilerFacade,
        $input = null,
        $output = null,
    ) {
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

        $this->printBanner($printer, $names, $values);

        while (true) {
            $this->write('break> ');

            $line = $this->readLine();
            if ($line === false) {
                // EOF, or an idle non-interactive input: resume rather than hang.
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

    /**
     * Reads one line. On an interactive terminal it blocks, waiting for the
     * user. On any non-interactive input (CI, pipe, cron, /dev/null) it polls
     * without blocking and gives up after a short timeout, so a `(break)` reached
     * with no human attached resumes instead of hanging the process forever.
     *
     * @return false|string the line, or false on EOF / idle non-interactive input
     */
    private function readLine(): string|false
    {
        if (stream_isatty($this->input)) {
            return fgets($this->input);
        }

        stream_set_blocking($this->input, false);
        $deadline = microtime(true) + self::NON_INTERACTIVE_READ_TIMEOUT_SECONDS;

        while (true) {
            $line = fgets($this->input);
            if ($line !== false) {
                return $line;
            }

            if (feof($this->input) || microtime(true) >= $deadline) {
                return false;
            }

            usleep(20_000);
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
