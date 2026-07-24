<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\HashSet;

use Phel\Lang\Collections\HashSet\PersistentHashSet;
use Phel\Lang\Collections\HashSet\TransientHashSet;
use Phel\Lang\Collections\Map\PersistentHashMap;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

final class TransientHashSetTest extends TestCase
{
    public function test_invoke_returns_the_member(): void
    {
        $set = $this->emptySet()->add(1)->add(2);

        self::assertSame(1, $set(1));
    }

    public function test_invoke_returns_null_for_a_missing_member(): void
    {
        $set = $this->emptySet()->add(1);

        self::assertNull($set(3));
    }

    public function test_to_string(): void
    {
        $set = $this->emptySet()->add(1);

        self::assertSame('<TransientSet count=1>', (string) $set);
    }

    public function test_remove_and_persistent(): void
    {
        $set = $this->emptySet()->add(1)->add(2)->remove(1);

        $persistent = $set->persistent();

        self::assertInstanceOf(PersistentHashSet::class, $persistent);
        self::assertFalse($persistent->contains(1));
        self::assertTrue($persistent->contains(2));
    }

    /**
     * @return TransientHashSet<int>
     */
    private function emptySet(): TransientHashSet
    {
        $hasher = new ModuloHasher();
        $map = PersistentHashMap::empty($hasher, new SimpleEqualizer());

        return new PersistentHashSet($hasher, null, $map)->asTransient();
    }
}
