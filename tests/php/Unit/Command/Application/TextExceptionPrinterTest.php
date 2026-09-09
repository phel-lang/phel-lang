<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Application;

use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Lang\AbstractType;
use Phel\Lang\FnInterface;
use Phel\Lang\SourceLocation;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\MungeInterface;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use TypeError;

use function ini_get;

final class TextExceptionPrinterTest extends TestCase
{
    private const string COLLAPSED_TRACE_HINT = '--stack-trace to show, full trace in .phel/error.log';

    public function test_renders_phel_source_location_for_evaluated_code_exception(): void
    {
        $original = new class('boom') extends TypeError {
            public function __construct(string $message)
            {
                parent::__construct($message);
                $this->line = 3;
            }
        };
        $compiledCode = "// my-file.phel\n// ;;AAAA\nphp_body();";
        $exception = EvaluatedCodeException::fromThrowableAndCompiledCode($original, $compiledCode);

        $exceptionPrinter = $this->createPrinter();

        $output = $exceptionPrinter->getStackTraceString($exception);

        self::assertStringContainsString('TypeError', $output);
        self::assertStringContainsString('boom', $output);
        self::assertStringContainsString('in my-file.phel:1', $output);
    }

    public function test_exception_string_renders_snippet_with_caret(): void
    {
        $file = 'example-file.phel';

        $codeSnippet = new CodeSnippet(
            startLocation: new SourceLocation($file, line: 1, column: 1),
            endLocation: new SourceLocation($file, line: 1, column: 3),
            code: '(+ 1 2 3 unknown-symbol)',
        );

        $type = $this->createStub(AbstractType::class);
        $type->method('getStartLocation')->willReturn(new SourceLocation($file, line: 1, column: 9));
        $type->method('getEndLocation')->willReturn(new SourceLocation($file, line: 1, column: 23));

        $exception = AnalyzerException::withLocation('.', $type);

        $expectedOutput = <<<'MSG'
.
in example-file.phel:1

1| (+ 1 2 3 unknown-symbol)
            ^^^^^^^^^^^^^^

MSG;

        $exceptionPrinter = $this->createPrinter();

        self::assertSame($expectedOutput, $exceptionPrinter->getExceptionString($exception, $codeSnippet));
    }

    public function test_user_facing_trace_maps_phel_frames_and_collapses_internal_ones(): void
    {
        $fn = new class() implements FnInterface {
            public const string BOUND_TO = 'app\\main\\level3';

            public function __invoke(): never
            {
                throw new RuntimeException('boom');
            }
        };

        try {
            $fn();
            self::fail('Expected exception');
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $exceptionPrinter = $this->createPrinter($this->stubMunge(), $this->stubFilePositionExtractor());

        $trace = $exceptionPrinter->getUserFacingTraceString($exception);

        self::assertStringContainsString('#0 /proj/src/main.phel:42 : (app\\main\\level3', $trace);
        self::assertMatchesRegularExpression('/\.\.\. \d+ internal frames?/', $trace);
        self::assertStringNotContainsString('PHPUnit', $trace);
    }

    public function test_user_facing_trace_renders_a_host_object_as_a_single_argument(): void
    {
        $fn = new class() implements FnInterface {
            public const string BOUND_TO = 'app\\main\\print_joined';

            public function __invoke(mixed $arg): never
            {
                throw new RuntimeException('boom');
            }
        };

        $ignoreArgs = (string) ini_get('zend.exception_ignore_args');
        // PHP records no trace arguments while this is on, and the arguments are what this test reads.
        ini_set('zend.exception_ignore_args', '0');

        try {
            $fn(new stdClass());
            self::fail('Expected exception');
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArgs);
        }

        $exceptionPrinter = $this->createPrinter(
            $this->stubMunge(),
            $this->stubFilePositionExtractor(),
            new ExceptionArgsPrinter(Printer::readable()),
        );

        $trace = $exceptionPrinter->getUserFacingTraceString($exception);

        self::assertStringContainsString('#0 /proj/src/main.phel:42 : (app\\main\\print_joined #<stdClass>)', $trace);
    }

    public function test_collapse_marker_names_the_flag_and_the_log_once_per_trace(): void
    {
        $exception = $this->throwFromNestedPhelFns();

        $trace = $this->createPrinter($this->stubMunge(), $this->stubFilePositionExtractor())
            ->getUserFacingTraceString($exception);

        self::assertSame(
            1,
            substr_count($trace, self::COLLAPSED_TRACE_HINT),
            "The hint rides on the first marker only:\n" . $trace,
        );
        self::assertGreaterThan(1, substr_count($trace, 'internal frame'), $trace);
        self::assertStringContainsString(
            '... 1 internal frame (' . self::COLLAPSED_TRACE_HINT . ')',
            $trace,
        );
    }

    public function test_stack_trace_flag_renders_the_frames_the_default_collapses(): void
    {
        $exception = $this->throwFromNestedPhelFns();
        $exceptionPrinter = $this->createPrinter($this->stubMunge(), $this->stubFilePositionExtractor());

        $collapsed = $exceptionPrinter->getUserFacingTraceString($exception);
        $full = $exceptionPrinter->getUserFacingTraceString($exception, showInternalFrames: true);

        self::assertStringNotContainsString('array_map', $collapsed);
        self::assertStringContainsString('array_map', $full);
        self::assertStringNotContainsString('internal frame', $full);
        self::assertStringNotContainsString(self::COLLAPSED_TRACE_HINT, $full);
    }

    public function test_user_facing_trace_is_empty_when_no_phel_frames_present(): void
    {
        $exceptionPrinter = $this->createPrinter();

        $trace = $exceptionPrinter->getUserFacingTraceString(new RuntimeException('plain php'));

        self::assertMatchesRegularExpression(
            '/^(\s*\.\.\. \d+ internal frames?( \(.+\))?\n?)?$/',
            $trace,
        );
        self::assertStringNotContainsString('#0', $trace);
    }

    public function test_a_frame_from_evaluated_code_reads_as_repl(): void
    {
        $exception = $this->throwFromNestedPhelFns();

        $evaluatedCode = $this->createStub(FilePositionExtractorInterface::class);
        $evaluatedCode->method('getOriginal')->willReturn(
            new FilePosition("/repo/src/php/Compiler/Domain/Evaluator/InMemoryEvaluator.php(26) : eval()'d code", 30),
        );

        $trace = $this->createPrinter($this->stubMunge(), $evaluatedCode)
            ->getUserFacingTraceString($exception);

        self::assertStringContainsString('#0 repl : (app\\main\\inner', $trace);
        self::assertStringNotContainsString('InMemoryEvaluator', $trace);
    }

    /**
     * A trace shaped `Phel fn -> PHP native -> Phel fn -> test runner`, so it
     * carries two separate runs of collapsible frames.
     */
    private function throwFromNestedPhelFns(): RuntimeException
    {
        $inner = new class() implements FnInterface {
            public const string BOUND_TO = 'app\\main\\inner';

            public function __invoke(int $x): never
            {
                throw new RuntimeException('boom ' . $x);
            }
        };

        $outer = new readonly class($inner) implements FnInterface {
            public const string BOUND_TO = 'app\\main\\outer';

            public function __construct(private FnInterface $inner) {}

            public function __invoke(): array
            {
                return array_map($this->inner, [1]);
            }
        };

        try {
            $outer();
            self::fail('Expected exception');
        } catch (RuntimeException $runtimeException) {
            return $runtimeException;
        }
    }

    private function stubMunge(): MungeInterface
    {
        $munge = $this->createStub(MungeInterface::class);
        $munge->method('decodeNs')->willReturnCallback(static fn(string $s): string => $s);

        return $munge;
    }

    private function stubFilePositionExtractor(): FilePositionExtractorInterface
    {
        $filePositionExtractor = $this->createStub(FilePositionExtractorInterface::class);
        $filePositionExtractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 42));

        return $filePositionExtractor;
    }

    private function createPrinter(
        ?MungeInterface $munge = null,
        ?FilePositionExtractorInterface $filePositionExtractor = null,
        ?ExceptionArgsPrinterInterface $exceptionArgsPrinter = null,
    ): TextExceptionPrinter {
        return new TextExceptionPrinter(
            $exceptionArgsPrinter ?? $this->createStub(ExceptionArgsPrinterInterface::class),
            $this->stubColorStyle(),
            $munge ?? $this->createStub(MungeInterface::class),
            $filePositionExtractor ?? $this->createStub(FilePositionExtractorInterface::class),
            $this->createStub(ErrorLogInterface::class),
            self::COLLAPSED_TRACE_HINT,
        );
    }

    private function stubColorStyle(): ColorStyleInterface
    {
        $colorStyle = $this->createStub(ColorStyleInterface::class);
        $colorStyle->method('blue')->willReturnCallback(static fn(string $msg): string => $msg);
        $colorStyle->method('red')->willReturnCallback(static fn(string $msg): string => $msg);

        return $colorStyle;
    }
}
