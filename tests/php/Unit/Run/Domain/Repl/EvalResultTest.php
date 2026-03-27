<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use ParseError;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Run\Domain\Repl\EvalError;
use Phel\Run\Domain\Repl\EvalResult;
use Phel\Run\Domain\Repl\StackFrame;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EvalResultTest extends TestCase
{
    protected function setUp(): void
    {
        GlobalEnvironmentSingleton::reset();
    }

    public function test_success_result(): void
    {
        $result = EvalResult::success(42);

        self::assertTrue($result->success);
        self::assertFalse($result->incomplete);
        self::assertSame(42, $result->value);
        self::assertNull($result->error);
        self::assertSame('', $result->output);
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

    public function test_success_result_with_output(): void
    {
        $result = EvalResult::success(null, "hello\n");

        self::assertTrue($result->success);
        self::assertNull($result->value);
        self::assertSame("hello\n", $result->output);
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

    public function test_from_eval_success(): void
    {
        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturn(42);

        $result = EvalResult::fromEval($facade, '(+ 1 1)');

        self::assertTrue($result->success);
        self::assertSame(42, $result->value);
        self::assertSame('', $result->output);
    }

    public function test_from_eval_captures_printed_output(): void
    {
        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturnCallback(static function (): mixed {
            echo "hello\n";

            return null;
        });

        $result = EvalResult::fromEval($facade, '(println "hello")');

        self::assertTrue($result->success);
        self::assertNull($result->value);
        self::assertSame("hello\n", $result->output);
    }

    public function test_from_eval_incomplete(): void
    {
        $loc = new SourceLocation('string', 1, 0);
        $snippet = new CodeSnippet($loc, $loc, '(+ 1');
        $token = new Token(Token::T_EOF, '', $loc, $loc);

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException(
            UnfinishedParserException::forSnippet($snippet, $token, 'Unexpected end of input'),
        );

        $result = EvalResult::fromEval($facade, '(+ 1');

        self::assertFalse($result->success);
        self::assertTrue($result->incomplete);
    }

    public function test_from_eval_compiler_exception(): void
    {
        $startLoc = new SourceLocation('test.phel', 3, 5);
        $endLoc = new SourceLocation('test.phel', 3, 10);

        $nested = new AnalyzerException(
            'Cannot resolve symbol',
            $startLoc,
            $endLoc,
        );

        $snippet = new CodeSnippet(
            $startLoc,
            $endLoc,
            '(unknown-fn)',
        );

        $compilerException = new CompilerException($nested, $snippet);

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException($compilerException);

        $result = EvalResult::fromEval($facade, '(unknown-fn)');

        self::assertFalse($result->success);
        self::assertFalse($result->incomplete);
        self::assertNotNull($result->error);
        self::assertSame('AnalyzerException', $result->error->exceptionClass);
        self::assertSame('Cannot resolve symbol', $result->error->message);
        self::assertSame('compile', $result->error->phase);
        self::assertSame('test.phel', $result->error->file);
        self::assertSame(3, $result->error->line);
        self::assertSame(5, $result->error->column);
        self::assertSame('(unknown-fn)', $result->error->codeSnippet);
    }

    public function test_from_eval_malformed_code(): void
    {
        $prev = new ParseError('syntax error', 0);
        $exception = CompiledCodeIsMalformedException::fromThrowable($prev);

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException($exception);

        $result = EvalResult::fromEval($facade, 'bad code');

        self::assertFalse($result->success);
        self::assertNotNull($result->error);
        self::assertSame('ParseError', $result->error->exceptionClass);
        self::assertSame('eval', $result->error->phase);
    }

    public function test_from_eval_runtime_exception(): void
    {
        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException(new RuntimeException('division by zero'));

        $result = EvalResult::fromEval($facade, '(/ 1 0)', new CompileOptions());

        self::assertFalse($result->success);
        self::assertNotNull($result->error);
        self::assertSame('RuntimeException', $result->error->exceptionClass);
        self::assertSame('division by zero', $result->error->message);
        self::assertSame('runtime', $result->error->phase);
        self::assertSame('', $result->output);
    }

    public function test_from_eval_captures_output_on_failure(): void
    {
        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturnCallback(static function (): never {
            echo 'partial output';
            throw new RuntimeException('boom');
        });

        $result = EvalResult::fromEval($facade, '(do (print "partial output") (/ 1 0))');

        self::assertFalse($result->success);
        self::assertNotNull($result->error);
        self::assertSame('partial output', $result->output);
    }

    public function test_from_eval_preserves_nested_output_buffering(): void
    {
        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturnCallback(static function (): mixed {
            echo 'inner';

            return null;
        });

        ob_start();
        echo 'outer-before:';
        $result = EvalResult::fromEval($facade, '(print "inner")');
        echo ':outer-after';
        $outerOutput = ob_get_clean();

        self::assertSame('inner', $result->output);
        self::assertSame('outer-before::outer-after', $outerOutput);
    }

    public function test_runtime_exception_produces_non_empty_frames(): void
    {
        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException(new RuntimeException('division by zero'));

        $result = EvalResult::fromEval($facade, '(/ 1 0)');

        self::assertNotNull($result->error);
        self::assertNotEmpty($result->error->frames);
        self::assertContainsOnlyInstancesOf(StackFrame::class, $result->error->frames);
    }

    public function test_runtime_exception_frames_have_file_and_line(): void
    {
        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException(new RuntimeException('error'));

        $result = EvalResult::fromEval($facade, '(error)');

        self::assertNotNull($result->error);
        $firstFrame = $result->error->frames[0];
        self::assertNotEmpty($firstFrame->file);
        self::assertGreaterThan(0, $firstFrame->line);
    }

    public function test_compiler_exception_produces_frames(): void
    {
        $startLoc = new SourceLocation('test.phel', 3, 5);
        $endLoc = new SourceLocation('test.phel', 3, 10);

        $nested = new AnalyzerException(
            'Cannot resolve symbol',
            $startLoc,
            $endLoc,
        );

        $snippet = new CodeSnippet($startLoc, $endLoc, '(unknown-fn)');
        $compilerException = new CompilerException($nested, $snippet);

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException($compilerException);

        $result = EvalResult::fromEval($facade, '(unknown-fn)');

        self::assertNotNull($result->error);
        self::assertIsArray($result->error->frames);
        self::assertContainsOnlyInstancesOf(StackFrame::class, $result->error->frames);
    }

    public function test_malformed_code_exception_produces_frames(): void
    {
        $prev = new ParseError('syntax error', 0);
        $exception = CompiledCodeIsMalformedException::fromThrowable($prev);

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException($exception);

        $result = EvalResult::fromEval($facade, 'bad code');

        self::assertNotNull($result->error);
        self::assertIsArray($result->error->frames);
        self::assertContainsOnlyInstancesOf(StackFrame::class, $result->error->frames);
    }

    public function test_success_result_has_no_frames(): void
    {
        $result = EvalResult::success(42);

        self::assertNull($result->error);
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

    public function test_from_eval_does_not_rollback_on_success(): void
    {
        $env = GlobalEnvironmentSingleton::initializeNew();
        $env->setNs('test-ns');
        $env->addRequireAlias('test-ns', Symbol::create('a'), Symbol::create('alias-ns'));

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturnCallback(static function () use ($env): mixed {
            $env->addRequireAlias('test-ns', Symbol::create('b'), Symbol::create('other-ns'));

            return 42;
        });

        $result = EvalResult::fromEval($facade, '(require alias-ns :as b)');

        self::assertTrue($result->success);
        self::assertTrue($env->hasRequireAlias('test-ns', Symbol::create('a')));
        self::assertTrue($env->hasRequireAlias('test-ns', Symbol::create('b')));
    }

    public function test_from_eval_rolls_back_namespace_on_runtime_error(): void
    {
        $env = GlobalEnvironmentSingleton::initializeNew();
        $env->setNs('original-ns');

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturnCallback(static function () use ($env): never {
            $env->setNs('dirty-ns');
            throw new RuntimeException('boom');
        });

        $result = EvalResult::fromEval($facade, '(ns dirty-ns)');

        self::assertFalse($result->success);
        self::assertSame('original-ns', $env->getNs());
    }

    public function test_from_eval_rolls_back_alias_on_compiler_error(): void
    {
        $env = GlobalEnvironmentSingleton::initializeNew();
        $env->setNs('test-ns');

        $startLoc = new SourceLocation('string', 1, 0);
        $endLoc = new SourceLocation('string', 1, 10);
        $nested = new AnalyzerException('fail', $startLoc, $endLoc);
        $snippet = new CodeSnippet($startLoc, $endLoc, '(ns ...)');
        $compilerException = new CompilerException($nested, $snippet);

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturnCallback(static function () use ($env, $compilerException): never {
            $env->addRequireAlias('test-ns', Symbol::create('dirty'), Symbol::create('dirty-ns'));
            throw $compilerException;
        });

        $result = EvalResult::fromEval($facade, '(ns test-ns (:require dirty-ns :as dirty))');

        self::assertFalse($result->success);
        self::assertFalse($env->hasRequireAlias('test-ns', Symbol::create('dirty')));
    }

    public function test_from_eval_rolls_back_on_malformed_code(): void
    {
        $env = GlobalEnvironmentSingleton::initializeNew();
        $env->setNs('test-ns');

        $prev = new ParseError('syntax error', 0);
        $exception = CompiledCodeIsMalformedException::fromThrowable($prev);

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturnCallback(static function () use ($env, $exception): never {
            $env->addUseAlias('test-ns', Symbol::create('DirtyClass'), Symbol::create('\\Dirty\\Class'));
            throw $exception;
        });

        $result = EvalResult::fromEval($facade, 'bad code');

        self::assertFalse($result->success);
        self::assertFalse($env->hasUseAlias('test-ns', Symbol::create('DirtyClass')));
    }

    public function test_from_eval_works_without_initialized_environment(): void
    {
        GlobalEnvironmentSingleton::reset();

        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willThrowException(new RuntimeException('boom'));

        $result = EvalResult::fromEval($facade, '(/ 1 0)');

        self::assertFalse($result->success);
        self::assertSame('RuntimeException', $result->error->exceptionClass);
    }
}
