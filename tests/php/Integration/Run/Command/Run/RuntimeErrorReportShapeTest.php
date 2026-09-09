<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\StackTraceOption;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PhelTest\Support\AssertsErrorReportShapeTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputInterface;

use function ob_get_clean;
use function ob_start;
use function preg_quote;

/**
 * One report shape for every class of uncaught runtime error (#3264). The
 * classes differ in what they can fill in, never in the order they fill it.
 */
final class RuntimeErrorReportShapeTest extends AbstractTestCommand
{
    use AssertsErrorReportShapeTrait;

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

    private function captureRunOutput(string $path, bool $stackTrace = false): string
    {
        ob_start();
        $this->createRunCommand()->run(
            $this->stubInput($path, $stackTrace),
            $this->stubOutput(),
        );

        return ob_get_clean() ?: '';
    }

    private function createRunCommand(): RunCommand
    {
        return new RunCommand();
    }

    private function stubInput(string $path, bool $stackTrace): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturnCallback(
            static fn(string $name): string|array => match ($name) {
                'path' => $path,
                'argv' => [],
                default => '',
            },
        );
        $input->method('getOption')->willReturnCallback(
            static fn(string $name): bool => $name === StackTraceOption::NAME && $stackTrace,
        );

        return $input;
    }
}
