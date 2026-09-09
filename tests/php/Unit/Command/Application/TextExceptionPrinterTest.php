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

        $exceptionPrinter = new TextExceptionPrinter(
            $this->createStub(ExceptionArgsPrinterInterface::class),
            $this->stubColorStyle(),
            $this->createStub(MungeInterface::class),
            $this->createStub(FilePositionExtractorInterface::class),
            $this->createStub(ErrorLogInterface::class),
        );

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

        $exceptionPrinter = new TextExceptionPrinter(
            $this->createStub(ExceptionArgsPrinterInterface::class),
            $this->stubColorStyle(),
            $this->createStub(MungeInterface::class),
            $this->createStub(FilePositionExtractorInterface::class),
            $this->createStub(ErrorLogInterface::class),
        );

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

        $exceptionPrinter = new TextExceptionPrinter(
            $this->createStub(ExceptionArgsPrinterInterface::class),
            $this->stubColorStyle(),
            $this->stubMunge(),
            $this->stubFilePositionExtractor(),
            $this->createStub(ErrorLogInterface::class),
        );

        $trace = $exceptionPrinter->getUserFacingTraceString($exception);

        self::assertStringContainsString('#0 /proj/src/main.phel:42 : (app\\main\\level3', $trace);
        self::assertMatchesRegularExpression('/\.\.\. \d+ internal frames?/', $trace);
        self::assertStringNotContainsString('PHPUnit', $trace);
    }

    public function test_user_facing_trace_renders_a_host_object_argument_as_one_arg(): void
    {
        $fn = new class() implements FnInterface {
            public const string BOUND_TO = 'app\\main\\print_joined';

            public function __invoke(mixed $arg): never
            {
                throw new RuntimeException('boom');
            }
        };

        // PHP omits trace arguments while this is on, and the arguments are what this test reads.
        $ignoreArgs = (string) ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');

        try {
            $fn(new stdClass());
            self::fail('Expected exception');
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArgs);
        }

        $exceptionPrinter = new TextExceptionPrinter(
            new ExceptionArgsPrinter(Printer::readable()),
            $this->stubColorStyle(),
            $this->stubMunge(),
            $this->stubFilePositionExtractor(),
            $this->createStub(ErrorLogInterface::class),
        );

        $trace = $exceptionPrinter->getUserFacingTraceString($exception);

        self::assertStringContainsString('#0 /proj/src/main.phel:42 : (app\\main\\print_joined #<stdClass>)', $trace);
    }

    public function test_user_facing_trace_is_empty_when_no_phel_frames_present(): void
    {
        $exceptionPrinter = new TextExceptionPrinter(
            $this->createStub(ExceptionArgsPrinterInterface::class),
            $this->stubColorStyle(),
            $this->createStub(MungeInterface::class),
            $this->createStub(FilePositionExtractorInterface::class),
            $this->createStub(ErrorLogInterface::class),
        );

        $trace = $exceptionPrinter->getUserFacingTraceString(new RuntimeException('plain php'));

        self::assertMatchesRegularExpression('/^(\s*\.\.\. \d+ internal frames?\n?)?$/', $trace);
        self::assertStringNotContainsString('#0', $trace);
    }

    private function stubColorStyle(): ColorStyleInterface
    {
        $colorStyle = $this->createStub(ColorStyleInterface::class);
        $colorStyle->method('blue')->willReturnCallback(static fn(string $msg): string => $msg);
        $colorStyle->method('red')->willReturnCallback(static fn(string $msg): string => $msg);

        return $colorStyle;
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
}
