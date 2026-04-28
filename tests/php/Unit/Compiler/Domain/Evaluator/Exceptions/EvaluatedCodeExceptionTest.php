<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Evaluator\Exceptions;

use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

final class EvaluatedCodeExceptionTest extends TestCase
{
    public function test_falls_back_to_raw_line_when_no_source_map_header(): void
    {
        $original = new RuntimeException('boom');
        $exception = EvaluatedCodeException::fromThrowableAndCompiledCode($original, 'phpcode_no_header');

        self::assertSame('string', $exception->getPhelFile());
        self::assertSame($original->getLine(), $exception->getPhelLine());
        self::assertSame('boom', $exception->getMessage());
        self::assertSame($original, $exception->getOriginalException());
    }

    public function test_extracts_filename_when_only_source_comment_present(): void
    {
        $original = new RuntimeException('boom', 0);
        $code = "// example.phel\n// no source map\n<?php // body";

        $exception = EvaluatedCodeException::fromThrowableAndCompiledCode($original, $code);

        self::assertSame('example.phel', $exception->getPhelFile());
    }

    public function test_maps_generated_line_to_original_phel_line(): void
    {
        // Mapping `;;AAAA` decodes to: generated line 3 -> original line 1, column 0.
        $compiledCode = "// my-file.phel\n// ;;AAAA\nphp_body();";

        $original = $this->throwableAtLine(3);

        $exception = EvaluatedCodeException::fromThrowableAndCompiledCode($original, $compiledCode);

        self::assertSame('my-file.phel', $exception->getPhelFile());
        self::assertSame(1, $exception->getPhelLine());
    }

    public function test_subtracts_header_offset_from_generated_line(): void
    {
        // Same mapping as above, but exception line is shifted by a one-line prefix
        // (e.g. when DebugLineTap prepends `declare(ticks=1);`).
        $compiledCode = "// my-file.phel\n// ;;AAAA\nphp_body();";

        $original = $this->throwableAtLine(4);

        $exception = EvaluatedCodeException::fromThrowableAndCompiledCode(
            $original,
            $compiledCode,
            headerOffset: 1,
        );

        self::assertSame(1, $exception->getPhelLine());
    }

    public function test_preserves_original_throwable_message_and_class(): void
    {
        $original = new TypeError('argument must be int, string given');
        $exception = EvaluatedCodeException::fromThrowableAndCompiledCode($original, '');

        self::assertSame('argument must be int, string given', $exception->getMessage());
        self::assertInstanceOf(TypeError::class, $exception->getOriginalException());
    }

    private function throwableAtLine(int $line): RuntimeException
    {
        return new class('boom', $line) extends RuntimeException {
            public function __construct(string $message, int $line)
            {
                parent::__construct($message);
                $this->line = $line;
            }
        };
    }
}
