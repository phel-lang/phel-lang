<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Domain\Exceptions;

use ArgumentCountError;
use Phel\Command\Domain\Exceptions\RuntimeArityMessage;
use Phel\Lang\AbstractFn;
use Phel\Shared\Munge;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class RuntimeArityMessageTest extends TestCase
{
    public function test_names_the_phel_fn_called_with_too_few_args(): void
    {
        $fn = new class() extends AbstractFn {
            public const BOUND_TO = 'my_app\\foo_bar\\add_it';

            public function __invoke(mixed $a, mixed $b): mixed
            {
                return $a;
            }
        };

        self::assertSame(
            'Wrong number of args (1) passed to my-app.foo-bar/add-it, expected 2',
            $this->message()->rewrite($this->thrownBy(static fn(): mixed => $fn(1))),
        );
    }

    public function test_names_a_fn_without_a_bound_name_as_fn(): void
    {
        $fn = new class() extends AbstractFn {
            public function __invoke(mixed $a, mixed $b): mixed
            {
                return $a;
            }
        };

        self::assertSame(
            'Wrong number of args (0) passed to fn, expected 2',
            $this->message()->rewrite($this->thrownBy(static fn(): mixed => $fn())),
        );
    }

    public function test_names_a_plain_closure_as_fn(): void
    {
        $closure = static fn(mixed $a, mixed $b): mixed => $a;

        self::assertSame(
            'Wrong number of args (1) passed to fn, expected 2',
            $this->message()->rewrite($this->thrownBy(static fn(): mixed => $closure(1))),
        );
    }

    public function test_leaves_a_php_function_call_alone(): void
    {
        $e = $this->thrownBy(static fn(): mixed => [self::class, 'twoArgs'](1));

        self::assertInstanceOf(ArgumentCountError::class, $e);
        self::assertNull($this->message()->rewrite($e));
    }

    public function test_leaves_other_exceptions_alone(): void
    {
        self::assertNull($this->message()->rewrite(new RuntimeException('Too few arguments')));
    }

    public static function twoArgs(mixed $a, mixed $b): mixed
    {
        return $a;
    }

    private function message(): RuntimeArityMessage
    {
        return new RuntimeArityMessage(new Munge());
    }

    private function thrownBy(callable $call): Throwable
    {
        try {
            $result = $call();
        } catch (Throwable $throwable) {
            return $throwable;
        }

        return $result instanceof Throwable ? $result : new RuntimeException('nothing thrown');
    }
}
