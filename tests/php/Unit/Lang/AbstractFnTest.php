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
}
