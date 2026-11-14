<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PhelTest\Support\AssertsErrorReportShapeTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;

use function dirname;
use function preg_quote;

use const PHP_BINARY;

/**
 * One report shape for every class of uncaught runtime error (#3264). The
 * classes differ in what they can fill in, never in the order they fill it.
 */
final class RuntimeErrorReportShapeTest extends AbstractTestCommand
{
    use AssertsErrorReportShapeTrait;
    use CapturesRunCommandOutputTrait;

    /**
     * Report per fixture and flag. Several tests read different parts of the
     * same report, and producing it means compiling and running the fixture.
     *
     * @var array<string, string>
     */
    private static array $reports = [];

    /**
     * The compiled-code index is written when a process exits, so each test's
     * fresh container would read it empty and compile the stdlib again. One
     * `phel run` here writes it once for every in-process run below.
     */
    public static function setUpBeforeClass(): void
    {
        Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 6) . '/bin/phel', 'run', 'Fixtures/ex-info-script.phel'],
            __DIR__,
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function runtimeErrorClassProvider(): iterable
    {
        yield 'not callable' => ['not-callable-script.phel', 'Value of type int is not callable'];
        yield 'core type error' => ['runtime-lib-error-script.phel', 'Expected a number, got string'];
        yield 'bounds' => ['core-error-script.phel', 'Vector index 5 out of bounds'];
        yield 'uncaught ex-info' => ['ex-info-script.phel', 'boom'];
        yield 'interop type error' => ['interop-type-error-script.phel', 'must be of type int, string given'];
        yield 'division by zero' => ['division-by-zero-script.phel', 'Division by zero'];
        yield 'java class call' => ['java-class-call-script.phel', 'Class "Integer" not found'];
        yield 'runtime arity' => ['arity-error-script.phel', 'Wrong number of args (1) passed to arity-error-script/add, expected 2'];
        yield 'runtime arity, underscore in the name' => ['arity-underscore-script.phel', 'Wrong number of args (1) passed to arity-underscore-script/add_it, expected 2'];
        yield 'seq on a scalar' => ['seq-on-scalar-script.phel', "Don't know how to create a seq from: int"];
    }

    #[DataProvider('runtimeErrorClassProvider')]
    public function test_every_runtime_error_class_reports_the_same_sections(string $fixture, string $message): void
    {
        $output = $this->reportFor($fixture);

        self::assertStringContainsString($message, $output);
        self::assertErrorReportShape($output);
    }

    #[DataProvider('runtimeErrorClassProvider')]
    public function test_the_at_line_names_the_users_own_file(string $fixture, string $message): void
    {
        $output = $this->reportFor($fixture);

        self::assertStringContainsString($message, $output);
        self::assertMatchesRegularExpression('~^  at .*/Fixtures/' . preg_quote($fixture, '~') . ':\d+$~m', $output);
    }

    #[DataProvider('runtimeErrorClassProvider')]
    public function test_the_stack_trace_flag_keeps_the_same_sections(string $fixture, string $message): void
    {
        $output = $this->reportFor($fixture, stackTrace: true);

        self::assertStringContainsString($message, $output);
        self::assertStringNotContainsString('internal frame', $output);
        self::assertErrorReportShape($output);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function codedRuntimeErrorProvider(): iterable
    {
        yield 'not callable' => ['not-callable-script.phel', 'PHEL400'];
        yield 'interop type error' => ['interop-type-error-script.phel', 'PHEL402'];
        yield 'bounds' => ['core-error-script.phel', 'PHEL403'];
        yield 'division by zero' => ['division-by-zero-script.phel', 'PHEL404'];
        yield 'runtime arity' => ['arity-error-script.phel', 'PHEL401'];
        yield 'seq on a scalar' => ['seq-on-scalar-script.phel', 'PHEL402'];
    }

    /**
     * A runtime failure carries the same searchable `[PHELxxx]` a compile error
     * does, so `phel explain` has something to look up (#3266).
     */
    #[DataProvider('codedRuntimeErrorProvider')]
    public function test_a_recognised_runtime_error_opens_with_its_code(string $fixture, string $code): void
    {
        $output = $this->reportFor($fixture);

        self::assertMatchesRegularExpression('~^\[' . $code . '\] \S~m', $output);
    }

    /**
     * A call to a Java class names the Phel replacement (#3465).
     */
    public function test_a_java_class_call_hints_at_the_phel_replacement(): void
    {
        $output = $this->reportFor('java-class-call-script.phel');

        self::assertStringContainsString('hint: Integer is a Java class, not a PHP one: for Integer/parseInt use (parse-long s).', $output);
    }

    /**
     * The headline names the Phel fn, never the PHP file that made the call
     * (#3459).
     */
    public function test_a_runtime_arity_headline_names_no_php_file(): void
    {
        $output = $this->reportFor('arity-error-script.phel');

        self::assertMatchesRegularExpression('~^\[PHEL401\] Wrong number of args \(1\) passed to arity-error-script/add, expected 2$~m', $output);
        self::assertStringNotContainsString('Too few arguments', $output);
    }

    public function test_a_missing_load_target_points_at_the_load_form(): void
    {
        $output = $this->reportFor('load-missing-script.phel');

        self::assertStringContainsString('Cannot locate missing for (load ...)', $output);
        self::assertMatchesRegularExpression('~^  at .*/Fixtures/load-missing-script\.phel:2$~m', $output);
    }

    public function test_a_loaded_file_in_another_namespace_points_at_the_load_form(): void
    {
        $output = $this->reportFor('load-wrong-ns-script.phel');

        self::assertStringContainsString('must use (in-ns load-wrong-ns-script)', $output);
        self::assertMatchesRegularExpression('~^  at .*/Fixtures/load-wrong-ns-script\.phel:2$~m', $output);
    }

    /**
     * An exception Phel does not recognise stays uncoded rather than being
     * labelled with a code that does not describe it.
     */
    public function test_an_uncaught_ex_info_carries_no_code(): void
    {
        $output = $this->reportFor('ex-info-script.phel');

        self::assertStringContainsString('boom', $output);
        self::assertDoesNotMatchRegularExpression('~\[PHEL\d{3}\]~', $output);
    }

    private function reportFor(string $fixture, bool $stackTrace = false): string
    {
        $key = $fixture . ($stackTrace ? '|stack-trace' : '');

        return self::$reports[$key] ??= $this->captureRunOutput(__DIR__ . '/Fixtures/' . $fixture, stackTrace: $stackTrace);
    }
}
