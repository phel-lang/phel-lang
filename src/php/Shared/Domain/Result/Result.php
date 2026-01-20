<?php

declare(strict_types=1);

namespace Phel\Shared\Domain\Result;

use LogicException;

/**
 * Result monad for representing success or failure.
 * Provides a functional approach to error handling without exceptions.
 *
 * @template T
 * @template E
 */
final readonly class Result
{
    /**
     * @param T|null $value
     * @param E|null $error
     */
    private function __construct(
        private mixed $value,
        private mixed $error,
        private bool $isSuccess,
    ) {
    }

    /**
     * Creates a success result with the given value.
     *
     * @template V
     *
     * @param V $value
     *
     * @return self<V, mixed>
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     */
    public static function success(mixed $value): self
    {
        return new self($value, null, true);
    }

    /**
     * Creates a failure result with the given error.
     *
     * @template F
     *
     * @param F $error
     *
     * @return self<mixed, F>
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     */
    public static function failure(mixed $error): self
    {
        return new self(null, $error, false);
    }

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess;
    }

    /**
     * Gets the value if successful, throws otherwise.
     *
     * @throws LogicException if result is a failure
     *
     * @return T
     */
    public function value(): mixed
    {
        if (!$this->isSuccess) {
            throw new LogicException('Cannot get value from a failure result');
        }

        return $this->value;
    }

    /**
     * Gets the error if failure, throws otherwise.
     *
     * @throws LogicException if result is a success
     *
     * @return E
     */
    public function error(): mixed
    {
        if ($this->isSuccess) {
            throw new LogicException('Cannot get error from a success result');
        }

        return $this->error;
    }

    /**
     * Gets the value or returns the default.
     *
     * @template D
     *
     * @param D $default
     *
     * @return D|T
     */
    public function valueOr(mixed $default): mixed
    {
        return $this->isSuccess ? $this->value : $default;
    }

    /**
     * Maps the success value using the given function.
     *
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return self<U, E>
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress PossiblyNullArgument
     */
    public function map(callable $fn): self
    {
        if (!$this->isSuccess) {
            return $this;
        }

        return self::success($fn($this->value));
    }

    /**
     * Maps the error using the given function.
     *
     * @template F
     *
     * @param callable(E): F $fn
     *
     * @return self<T, F>
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress PossiblyNullArgument
     */
    public function mapError(callable $fn): self
    {
        if ($this->isSuccess) {
            return $this;
        }

        return self::failure($fn($this->error));
    }
}
