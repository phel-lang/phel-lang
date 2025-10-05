<?php

declare(strict_types=1);

namespace PhelTest\Unit\Phel;

use Phel;
use PHPUnit\Framework\TestCase;

final class PhelConstructorsTest extends TestCase
{
    public function test_vector(): void
    {
        $vector = Phel::vector([1, 2]);
        self::assertSame([1, 2], $vector->toArray());
    }

    public function test_list(): void
    {
        $list = Phel::list([1, 2]);
        self::assertSame([1, 2], $list->toArray());
    }

    public function test_map(): void
    {
        $map = Phel::map('a', 1, 'b', 2);
        self::assertSame(['a' => 1, 'b' => 2], iterator_to_array($map));
    }

    public function test_map_from_array(): void
    {
        $map = Phel::map(['a', 1, 'b', 2]);
        self::assertSame(['a' => 1, 'b' => 2], iterator_to_array($map));
    }

    public function test_set(): void
    {
        $set = Phel::set([1, 2]);
        self::assertSame([1, 2], $set->toPhpArray());
    }

    public function test_vector_with_null(): void
    {
        $vector = Phel::vector(null);
        self::assertSame([], $vector->toArray());
    }

    public function test_list_with_null(): void
    {
        $list = Phel::list(null);
        self::assertSame([], $list->toArray());
    }

    public function test_map_with_null(): void
    {
        $map = Phel::map(null);
        self::assertSame([], iterator_to_array($map));
    }

    public function test_set_with_null(): void
    {
        $set = Phel::set(null);
        self::assertSame([], $set->toPhpArray());
    }
}
