<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PhelTest\Support\AssertsErrorReportShapeTrait;
use PHPUnit\Framework\Attributes\DataProvider;

use function preg_quote;

/**
 * One report shape for every class of uncaught runtime error (#3264). The
 * classes differ in what they can fill in, never in the order they fill it.
 */
final class RuntimeErrorReportShapeTest extends AbstractTestCommand
{
    use AssertsErrorReportShapeTrait;
    use CapturesRunCommandOutputTrait;

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
    }

    #[DataProvider('runtimeErrorClassProvider')]
    public function test_every_runtime_error_class_reports_the_same_sections(string $fixture, string $message): void
    {
        $output = $this->captureRunOutput(__DIR__ . '/Fixtures/' . $fixture);

        self::assertStringContainsString($message, $output);
        self::assertErrorReportShape($output);
    }

    #[DataProvider('runtimeErrorClassProvider')]
    public function test_the_at_line_names_the_users_own_file(string $fixture, string $message): void
    {
        $output = $this->captureRunOutput(__DIR__ . '/Fixtures/' . $fixture);

        self::assertStringContainsString($message, $output);
        self::assertMatchesRegularExpression('~^  at .*/Fixtures/' . preg_quote($fixture, '~') . ':\d+$~m', $output);
    }

    #[DataProvider('runtimeErrorClassProvider')]
    public function test_the_stack_trace_flag_keeps_the_same_sections(string $fixture, string $message): void
    {
        $output = $this->captureRunOutput(__DIR__ . '/Fixtures/' . $fixture, stackTrace: true);

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
    }

    /**
     * A runtime failure carries the same searchable `[PHELxxx]` a compile error
     * does, so `phel explain` has something to look up (#3266).
     */
    #[DataProvider('codedRuntimeErrorProvider')]
    public function test_a_recognised_runtime_error_opens_with_its_code(string $fixture, string $code): void
    {
        $output = $this->captureRunOutput(__DIR__ . '/Fixtures/' . $fixture);

        self::assertMatchesRegularExpression('~^\[' . $code . '\] \S~m', $output);
    }

    /**
     * An exception Phel does not recognise stays uncoded rather than being
     * labelled with a code that does not describe it.
     */
    public function test_an_uncaught_ex_info_carries_no_code(): void
    {
        $output = $this->captureRunOutput(__DIR__ . '/Fixtures/ex-info-script.phel');

        self::assertStringContainsString('boom', $output);
        self::assertDoesNotMatchRegularExpression('~\[PHEL\d{3}\]~', $output);
    }
}
