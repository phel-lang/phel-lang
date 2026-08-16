<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

/**
 * Both construction paths have to agree on where a map stops being a flat
 * array.
 *
 * `TypeFactory::persistentMapFromArray()` compared the flat `[k1, v1, ...]`
 * element count against `MAX_SIZE`, which counts entries, so a `{...}` literal
 * promoted at half the constant while `assoc`, `into` and `zipmap` promoted at
 * the constant. The same map then had two representations and a 3.4x read
 * difference depending on how it was built (#3172).
 *
 * Everything here is expressed in terms of the constant, so it keeps meaning
 * the same thing if the threshold moves again.
 */
final class MapPromotionThresholdTest extends TestCase
{
    public function test_a_literal_at_the_threshold_stays_an_array_map(): void
    {
        $map = $this->literalOfSize(PersistentArrayMap::MAX_SIZE);

        self::assertInstanceOf(PersistentArrayMap::class, $map);
        self::assertCount(PersistentArrayMap::MAX_SIZE, $map);
    }

    public function test_a_literal_one_past_the_threshold_is_a_hash_map(): void
    {
        $map = $this->literalOfSize(PersistentArrayMap::MAX_SIZE + 1);

        self::assertInstanceOf(PersistentHashMap::class, $map);
        self::assertCount(PersistentArrayMap::MAX_SIZE + 1, $map);
    }

    public function test_a_grown_map_promotes_at_the_same_size_as_a_literal(): void
    {
        $atThreshold = $this->grownOfSize(PersistentArrayMap::MAX_SIZE);
        $pastThreshold = $this->grownOfSize(PersistentArrayMap::MAX_SIZE + 1);

        self::assertInstanceOf(PersistentArrayMap::class, $atThreshold);
        self::assertInstanceOf(PersistentHashMap::class, $pastThreshold);
    }

    public function test_both_paths_agree_at_every_size_around_the_threshold(): void
    {
        for ($size = 1; $size <= PersistentArrayMap::MAX_SIZE + 3; ++$size) {
            self::assertSame(
                $this->literalOfSize($size)::class,
                $this->grownOfSize($size)::class,
                'a map of ' . $size . ' entries has one representation, however it was built',
            );
        }
    }

    public function test_the_representation_does_not_change_what_the_map_holds(): void
    {
        $size = PersistentArrayMap::MAX_SIZE + 1;
        $literal = $this->literalOfSize($size);
        $grown = $this->grownOfSize($size);

        self::assertTrue($literal->equals($grown));
        for ($i = 0; $i < $size; ++$i) {
            self::assertSame($i, $grown->find(Keyword::create('k' . $i)));
        }
    }

    private function literalOfSize(int $size): mixed
    {
        return new TypeFactory(new Hasher(), new Equalizer())->persistentMapFromArray($this->kvs($size));
    }

    private function grownOfSize(int $size): mixed
    {
        $transient = PersistentArrayMap::empty(new Hasher(), new Equalizer())->asTransient();
        for ($i = 0; $i < $size; ++$i) {
            $transient->put(Keyword::create('k' . $i), $i);
        }

        return $transient->persistent();
    }

    /**
     * @return list<mixed>
     */
    private function kvs(int $size): array
    {
        $kvs = [];
        for ($i = 0; $i < $size; ++$i) {
            $kvs[] = Keyword::create('k' . $i);
            $kvs[] = $i;
        }

        return $kvs;
    }
}
