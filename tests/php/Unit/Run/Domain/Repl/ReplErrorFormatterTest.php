<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Error;
use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Run\Domain\Repl\Hint\NotCallableHint;
use Phel\Run\Domain\Repl\ReplErrorFormatter;
use Phel\Shared\ColorStyle;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ReplErrorFormatterTest extends TestCase
{
    public function test_filters_internal_compiler_frames(): void
    {
        $trace = implode(PHP_EOL, [
            'Error: boom',
            'in string:1 (gen: ...)',
            '',
            '#0 /repo/phel-lang/src/php/Compiler/Domain/Evaluator/InMemoryEvaluator.php(26): eval()',
            '#1 /repo/phel-lang/src/php/Run/Infrastructure/Command/ReplCommand.php(214): Foo->bar()',
            '#2 /home/user/code/myproject.phel(3): user_call()',
        ]);

        $formatter = $this->buildFormatter($trace);
        $result = $formatter->format(new RuntimeException('boom'));

        self::assertStringContainsString('myproject.phel', $result->trace);
        self::assertStringNotContainsString('InMemoryEvaluator', $result->trace);
        self::assertStringNotContainsString('ReplCommand.php', $result->trace);
        self::assertStringContainsString('2 internal frames hidden', $result->trace);
    }

    public function test_drops_prefix_lines_before_first_frame(): void
    {
        $trace = implode(PHP_EOL, [
            'Error: original message',
            'in string:5 (gen: /tmp/eval.php:3)',
            '',
            '#0 /home/user/code/myproject.phel(3): user_call()',
        ]);

        $formatter = $this->buildFormatter($trace);
        $result = $formatter->format(new RuntimeException('original message'));

        self::assertStringNotContainsString('Error: original message', $result->trace);
        self::assertStringNotContainsString('in string:5', $result->trace);
        self::assertStringContainsString('#0 /home/user/code/myproject.phel', $result->trace);
    }

    public function test_filters_symfony_console_and_bin_phel_frames(): void
    {
        $trace = implode(PHP_EOL, [
            '#0 /home/runner/work/phel-lang/phel-lang/vendor/symfony/console/Application.php(195): Foo->run()',
            '#1 /home/runner/work/phel-lang/phel-lang/bin/phel(97): Bar->call()',
            '#2 /home/user/code/myproject.phel(3): user_call()',
        ]);

        $formatter = $this->buildFormatter($trace);
        $result = $formatter->format(new RuntimeException('boom'));

        self::assertStringNotContainsString('symfony/console', $result->trace);
        self::assertStringNotContainsString('bin/phel', $result->trace);
        self::assertStringContainsString('myproject.phel', $result->trace);
        self::assertStringContainsString('2 internal frames hidden', $result->trace);
    }

    public function test_unwraps_evaluated_code_exception_for_headline_and_hint(): void
    {
        $original = new Error('Object of type Phel\\Lang\\Collections\\LazySeq\\ChunkedSeq is not callable');
        $wrapped = EvaluatedCodeException::fromThrowableAndCompiledCode($original, "// some.phel\n", 0);

        $formatter = new ReplErrorFormatter(
            [new NotCallableHint()],
            $this->stubPrinter(''),
            ColorStyle::noStyles(),
        );

        $rendered = $formatter->render($wrapped);

        self::assertStringContainsString('Error: Object of type', $rendered);
        self::assertStringNotContainsString('EvaluatedCodeException', $rendered);
        self::assertStringContainsString("'sequence'", $rendered);
    }

    public function test_renders_headline_with_short_class_name(): void
    {
        $formatter = $this->buildFormatter('');

        $rendered = $formatter->render(new RuntimeException('something broke'));

        self::assertStringContainsString('RuntimeException: something broke', $rendered);
    }

    public function test_includes_hint_when_match(): void
    {
        $formatter = new ReplErrorFormatter(
            [new NotCallableHint()],
            $this->stubPrinter(''),
            ColorStyle::noStyles(),
        );

        $rendered = $formatter->render(
            new Error('Object of type Phel\\Lang\\Collections\\LazySeq\\ChunkedSeq is not callable'),
        );

        self::assertStringContainsString('hint:', $rendered);
        self::assertStringContainsString("'sequence'", $rendered);
    }

    public function test_no_hint_section_when_no_match(): void
    {
        $formatter = new ReplErrorFormatter(
            [new NotCallableHint()],
            $this->stubPrinter(''),
            ColorStyle::noStyles(),
        );

        $rendered = $formatter->render(new RuntimeException('unrelated'));

        self::assertStringNotContainsString('hint:', $rendered);
    }

    public function test_empty_message_falls_back_to_no_message_marker(): void
    {
        $formatter = $this->buildFormatter('');

        $rendered = $formatter->render(new RuntimeException(''));

        self::assertStringContainsString('*no message*', $rendered);
    }

    private function buildFormatter(string $trace): ReplErrorFormatter
    {
        return new ReplErrorFormatter(
            [],
            $this->stubPrinter($trace),
            ColorStyle::noStyles(),
        );
    }

    private function stubPrinter(string $trace): ExceptionPrinterInterface
    {
        return new readonly class($trace) implements ExceptionPrinterInterface {
            public function __construct(private string $trace) {}

            public function getStackTraceString(Throwable $e): string
            {
                return $this->trace;
            }

            public function printStackTrace(Throwable $e): void {}

            public function printException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void {}

            public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
            {
                return '';
            }

            public function printError(string $error): void {}
        };
    }
}
