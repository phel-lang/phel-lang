<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Exceptions;

use ArgumentCountError;
use DivisionByZeroError;
use Error;
use OutOfBoundsException;
use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\RuntimeErrorCodeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class RuntimeErrorCodeResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{0: Throwable, 1: ?ErrorCode}>
     */
    public static function throwableProvider(): iterable
    {
        yield 'a missing argument is an arity error, not a type error' => [
            new ArgumentCountError('Too few arguments to function foo(), 1 passed and exactly 2 expected'),
            ErrorCode::RUNTIME_ARITY_ERROR,
        ];

        yield 'an interop type error' => [
            new TypeError('str_repeat(): Argument #2 ($times) must be of type int, string given'),
            ErrorCode::RUNTIME_TYPE_ERROR,
        ];

        yield 'a division by zero' => [
            new DivisionByZeroError('Division by zero'),
            ErrorCode::DIVISION_BY_ZERO,
        ];

        yield 'a typed collection index' => [
            new IndexOutOfBoundsException('Vector index 5 out of bounds'),
            ErrorCode::INDEX_OUT_OF_BOUNDS,
        ];

        yield 'the SPL bounds error core nth throws' => [
            new OutOfBoundsException('Index out of bounds'),
            ErrorCode::INDEX_OUT_OF_BOUNDS,
        ];

        yield 'calling a scalar' => [
            new Error('Value of type int is not callable'),
            ErrorCode::RUNTIME_NOT_CALLABLE,
        ];

        yield 'calling an object' => [
            new Error('Object of type Phel\Lang\Keyword is not callable'),
            ErrorCode::RUNTIME_NOT_CALLABLE,
        ];

        yield 'a wrapped cause is unwrapped one level' => [
            new RuntimeException('Cannot evaluate code', 0, new DivisionByZeroError('Division by zero')),
            ErrorCode::DIVISION_BY_ZERO,
        ];

        yield 'a plain runtime exception has no code' => [new RuntimeException('boom'), null];

        yield 'an unrecognised engine error has no code' => [new Error('something else went wrong'), null];
    }

    #[DataProvider('throwableProvider')]
    public function test_it_resolves_the_code_of_a_runtime_error(Throwable $throwable, ?ErrorCode $expected): void
    {
        self::assertSame($expected, RuntimeErrorCodeResolver::codeFor($throwable));
    }
}
