<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Error;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Run\Domain\Repl\ReplErrorFormatter;
use Phel\Shared\ColorStyle;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\NotCallableHint;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
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

    public function test_keeps_phel_fn_frames_even_when_gen_path_is_internal(): void
    {
        $trace = implode(PHP_EOL, [
            "#0 /repo/phel-lang/src/php/Compiler/Domain/Evaluator/InMemoryEvaluator.php(26) : eval()'d code:30 (gen: /repo/phel-lang/src/php/Compiler/Domain/Evaluator/InMemoryEvaluator.php(26) : eval()'d code:30) : (user\\f3)",
            '#1 /repo/phel-lang/src/php/Compiler/Application/EvalCompiler.php(116): Foo->bar()',
        ]);

        $formatter = $this->buildFormatter($trace);
        $result = $formatter->format(new RuntimeException('boom'));

        self::assertStringContainsString('(user\\f3)', $result->trace);
        self::assertStringContainsString('1 internal frame hidden', $result->trace);
    }

    public function test_compacts_eval_code_frames_to_repl_location(): void
    {
        $trace = "#0 /repo/src/php/Compiler/Domain/Evaluator/InMemoryEvaluator.php(26) : eval()'d code:30 (gen: /repo/src/php/Compiler/Domain/Evaluator/InMemoryEvaluator.php(26) : eval()'d code:30) : (user\\f3)";

        $formatter = $this->buildFormatter($trace);
        $result = $formatter->format(new RuntimeException('boom'));

        self::assertSame('#0 repl : (user\\f3)', $result->trace);
    }

    public function test_strips_generated_location_from_mapped_phel_frames(): void
    {
        $trace = '#0 /proj/src/main.phel:6 (gen: /tmp/phel/__phel_abc.php:23) : (app\\main\\level3 3)';

        $formatter = $this->buildFormatter($trace);
        $result = $formatter->format(new RuntimeException('boom'));

        self::assertSame('#0 /proj/src/main.phel:6 : (app\\main\\level3 3)', $result->trace);
    }

    public function test_unwraps_evaluated_code_exception_for_headline_and_hint(): void
    {
        $original = new Error('Object of type Phel\\Lang\\Collections\\LazySeq\\ChunkedSeq is not callable');
        $wrapped = EvaluatedCodeException::fromThrowableAndCompiledCode($original, "// some.phel\n", 0);

        $formatter = new ReplErrorFormatter(
            new ExceptionHintResolver([new NotCallableHint()]),
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
            new ExceptionHintResolver([new NotCallableHint()]),
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
            new ExceptionHintResolver([new NotCallableHint()]),
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

    public function test_strips_eval_path_from_php_injected_message(): void
    {
        $formatter = $this->buildFormatter('');
        $message = "Too few arguments to function Foo::bar(), 0 passed in /Users/dev/repo/src/php/Compiler/Domain/Evaluator/InMemoryEvaluator.php(26) : eval()'d code on line 3 and exactly 1 expected";

        $rendered = $formatter->render(new RuntimeException($message));

        self::assertStringContainsString('0 passed and exactly 1 expected', $rendered);
        self::assertStringNotContainsString('InMemoryEvaluator.php', $rendered);
        self::assertStringNotContainsString("eval()'d code", $rendered);
    }

    public function test_leaves_message_untouched_when_no_eval_path_present(): void
    {
        $formatter = $this->buildFormatter('');

        $rendered = $formatter->render(new RuntimeException('plain user-provided message'));

        self::assertStringContainsString('plain user-provided message', $rendered);
    }

    private function buildFormatter(string $trace): ReplErrorFormatter
    {
        return new ReplErrorFormatter(
            new ExceptionHintResolver([]),
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

            public function getUserFacingTraceString(Throwable $e): string
            {
                return '';
            }

            public function printException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void {}

            public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
            {
                return '';
            }

            public function printError(string $error): void {}
        };
    }
}
