<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\AbstractFn;
use PHPUnit\Framework\TestCase;

final class AbstractFnTest extends TestCase
{
    public function test_to_string_renders_function(): void
    {
        $fn = new class() extends AbstractFn {
            public function __invoke(...$args): mixed
            {
                return 'test';
            }
        };

        $result = (string) $fn;

        self::assertSame('<function>', $result);
    }

    public function test_string_concatenation_with_function(): void
    {
        $fn = new class() extends AbstractFn {
            public function __invoke(...$args): mixed
            {
                return 'test';
            }
        };

        $result = 'Hello, ' . $fn . '!';

        self::assertSame('Hello, <function>!', $result);
    }

    public function test_to_string_renders_named_function(): void
    {
        $fn = new class() extends AbstractFn {
            public const BOUND_TO = 'phel\\core\\my_func';

            public function __invoke(...$args): mixed
            {
                return 'test';
            }
        };

        self::assertSame('<function:my-func>', (string) $fn);
    }

    public function test_string_concatenation_with_named_function(): void
    {
        $fn = new class() extends AbstractFn {
            public const BOUND_TO = 'phel\\core\\name';

            public function __invoke(...$args): mixed
            {
                return 'test';
            }
        };

        $result = 'Hello, ' . $fn . '!';

        self::assertSame('Hello, <function:name>!', $result);
    }

    public function test_to_string_does_not_deprecate_when_bound_to_is_absent(): void
    {
        $fn = new class() extends AbstractFn {
            public function __invoke(...$args): mixed
            {
                return 'test';
            }
        };

        $deprecations = [];
        set_error_handler(
            static function (int $errno, string $message) use (&$deprecations): bool {
                $deprecations[] = $message;
                return true;
            },
            E_DEPRECATED,
        );

        try {
            $rendered = (string) $fn;
        } finally {
            restore_error_handler();
        }

        self::assertSame('<function>', $rendered);
        self::assertSame([], $deprecations);
    }
}
