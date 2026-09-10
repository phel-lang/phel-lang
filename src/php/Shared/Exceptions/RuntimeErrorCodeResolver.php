<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

use ArgumentCountError;
use DivisionByZeroError;
use Error;
use OutOfBoundsException;
use Throwable;
use TypeError;

/**
 * Maps an uncaught runtime failure to its PHEL4xx code, or null when no class
 * matches. The cause is unwrapped one level, the way `ExceptionHintResolver`
 * does it, because a failure inside evaluated code arrives wrapped.
 *
 * Only the recognised runtime error classes get a code. A user's own
 * `ex-info` stays uncoded: it is their exception, not one Phel raises.
 */
final readonly class RuntimeErrorCodeResolver
{
    /**
     * PHP words the engine's "you called a non-function" error two ways, by
     * whether the value is a scalar or an object.
     */
    private const string NOT_CALLABLE_MESSAGE = '/(?:Object|Value) of type [\w\\\\]+ is not callable/';

    public static function codeFor(Throwable $e): ?ErrorCode
    {
        foreach ([$e, $e->getPrevious()] as $candidate) {
            if (!$candidate instanceof Throwable) {
                continue;
            }

            $code = self::classify($candidate);
            if ($code instanceof ErrorCode) {
                return $code;
            }
        }

        return null;
    }

    private static function classify(Throwable $e): ?ErrorCode
    {
        // ArgumentCountError extends TypeError, so it has to be asked first.
        if ($e instanceof ArgumentCountError) {
            return ErrorCode::RUNTIME_ARITY_ERROR;
        }

        if ($e instanceof DivisionByZeroError) {
            return ErrorCode::DIVISION_BY_ZERO;
        }

        if ($e instanceof TypeError) {
            return ErrorCode::RUNTIME_TYPE_ERROR;
        }

        if ($e instanceof OutOfBoundsException) {
            return ErrorCode::INDEX_OUT_OF_BOUNDS;
        }

        if ($e instanceof Error && preg_match(self::NOT_CALLABLE_MESSAGE, $e->getMessage()) === 1) {
            return ErrorCode::RUNTIME_NOT_CALLABLE;
        }

        return null;
    }
}
