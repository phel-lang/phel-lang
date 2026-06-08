<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Eval;

use Phel\Shared\Eval\EvalError;
use Phel\Shared\Eval\EvalResult;
use Phel\Shared\Eval\StackFrame;
use PHPUnit\Framework\TestCase;

final class EvalResultTest extends TestCase
{
    public function test_success_result(): void
    {
        $result = EvalResult::success(42);

        self::assertTrue($result->success);
        self::assertFalse($result->incomplete);
        self::assertSame(42, $result->value);
        self::assertNull($result->error);
        self::assertSame('', $result->output);
    }

    public function test_success_result_with_output(): void
    {
        $result = EvalResult::success(null, "hello\n");

        self::assertTrue($result->success);
        self::assertNull($result->value);
        self::assertSame("hello\n", $result->output);
    }

    public function test_success_result_has_no_frames(): void
    {
        $result = EvalResult::success(42);

        self::assertNull($result->error);
    }

    public function test_incomplete_result(): void
    {
        $result = EvalResult::incomplete();

        self::assertFalse($result->success);
        self::assertTrue($result->incomplete);
        self::assertNull($result->value);
        self::assertNull($result->error);
        self::assertSame('', $result->output);
    }

    public function test_failure_result(): void
    {
        $error = new EvalError(
            exceptionClass: 'TestException',
            message: 'test',
            errorCode: null,
            file: null,
            line: null,
            column: null,
            endLine: null,
            endColumn: null,
            codeSnippet: null,
            stackTrace: '',
            phase: 'runtime',
        );

        $result = EvalResult::failure($error, 'partial');

        self::assertFalse($result->success);
        self::assertFalse($result->incomplete);
        self::assertSame($error, $result->error);
        self::assertSame('partial', $result->output);
    }

    public function test_stack_frame_value_object(): void
    {
        $frame = new StackFrame(
            file: '/path/to/file.php',
            line: 42,
            class: 'MyClass',
            function: 'myMethod',
        );

        self::assertSame('/path/to/file.php', $frame->file);
        self::assertSame(42, $frame->line);
        self::assertSame('MyClass', $frame->class);
        self::assertSame('myMethod', $frame->function);
    }

    public function test_stack_frame_with_nullable_fields(): void
    {
        $frame = new StackFrame(
            file: '/path/to/file.php',
            line: 10,
            class: null,
            function: null,
        );

        self::assertNull($frame->class);
        self::assertNull($frame->function);
    }

    public function test_failure_with_default_empty_frames(): void
    {
        $error = new EvalError(
            exceptionClass: 'TestException',
            message: 'test',
            errorCode: null,
            file: null,
            line: null,
            column: null,
            endLine: null,
            endColumn: null,
            codeSnippet: null,
            stackTrace: '',
            phase: 'runtime',
        );

        self::assertSame([], $error->frames);
    }
}
