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
use Phel\Lang\SourceLocation;
use Phel\Run\Domain\Repl\EvalResult;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EvalResultTest extends TestCase
{
    public function test_success_result(): void
    {
        $result = EvalResult::success(42);

        self::assertTrue($result->success);
        self::assertFalse($result->incomplete);
        self::assertSame(42, $result->value);
        self::assertNull($result->error);
    }

    public function test_incomplete_result(): void
    {
        $result = EvalResult::incomplete();

        self::assertFalse($result->success);
        self::assertTrue($result->incomplete);
        self::assertNull($result->value);
        self::assertNull($result->error);
    }

    public function test_from_eval_success(): void
    {
        $facade = $this->createMock(CompilerFacadeInterface::class);
        $facade->method('eval')->willReturn(42);

        $result = EvalResult::fromEval($facade, '(+ 1 1)');

        self::assertTrue($result->success);
        self::assertSame(42, $result->value);
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
    }
}
