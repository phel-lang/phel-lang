<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Generator;
use Phel\Lang\Seq;
use PHPUnit\Framework\TestCase;

final class SeqTest extends TestCase
{
    public function test_first_of_an_array(): void
    {
        self::assertSame(1, Seq::first([1, 2, 3]));
    }

    public function test_first_of_an_empty_iterable_is_null(): void
    {
        self::assertNull(Seq::first([]));
    }

    public function test_first_keeps_a_falsy_element(): void
    {
        self::assertSame(0, Seq::first([0, 1]));
        self::assertFalse(Seq::first([false, 1]));
    }

    public function test_first_pulls_exactly_one_element(): void
    {
        $pulled = 0;
        $generator = (static function () use (&$pulled): Generator {
            foreach ([10, 20, 30] as $value) {
                ++$pulled;
                yield $value;
            }
        })();

        self::assertSame(10, Seq::first($generator));
        self::assertSame(1, $pulled, 'first must not drain the source');
    }

    public function test_first_of_an_infinite_generator_terminates(): void
    {
        $generator = (static function (): Generator {
            $i = 0;
            while (true) {
                yield $i++;
            }
        })();

        self::assertSame(0, Seq::first($generator));
    }
}
