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
}
