<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections;

use Closure;
use Phel\Lang\BigInt;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use PhelTest\Unit\Lang\Collections\Struct\FakeStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;

use const INF;
use const NAN;

/**
 * Writing the value a key already holds hands the receiver back instead of a
 * copy (#3321), on every persistent collection with keyed writes. "Already
 * holds" means identical (`===`) and not merely `=`: a value that is equal but
 * distinguishable, a signed zero or a `BigInt` over an `int` or a vector
 * carrying different metadata, is still written. So is any PHP array, since
 * `===` walks arrays rather than comparing them by identity.
 *
 * Each flavour is a closure building a collection that holds `$stored` under
 * the returned key, so every scenario runs against every storage shape: the
 * flat array map, the trie (including the `nil` slot and a hash collision
 * node), the sorted map, a struct, both halves of a vector, and the
 * sub vector that `rest` and `subvec` return.
 */
final class PutUnchangedValueTest extends TestCase
{
    public static function provideFlavours(): iterable
    {
        $factory = TypeFactory::getInstance();
        $key = Keyword::create('k');

        yield 'array map' => [static fn(mixed $stored): array => [
            $factory->persistentMapFromKVs($key, $stored),
            $key,
        ]];

        yield 'hash map' => [static function (mixed $stored) use ($factory, $key): array {
            $kvs = [];
            for ($i = 0; $i < 90; ++$i) {
                $kvs[] = Keyword::create('k' . $i);
                $kvs[] = $i;
            }

            return [
                $factory->persistentMapFromArray([...$kvs, $key, $stored]),
                $key,
            ];
        }];

        yield 'hash map nil key' => [static fn(mixed $stored): array => [
            PersistentHashMap::empty($factory->getHasher(), $factory->getEqualizer())->put(1, 1)->put(null, $stored),
            null,
        ]];

        yield 'hash collision node' => [static fn(mixed $stored): array => [
            PersistentHashMap::empty(new ModuloHasher(1), $factory->getEqualizer())->put(1, 1)->put(2, $stored),
            2,
        ]];

        yield 'sorted map' => [static fn(mixed $stored): array => [
            $factory->persistentSortedMapFromArray([$key, $stored]),
            $key,
        ]];

        yield 'struct' => [static fn(mixed $stored): array => [
            FakeStruct::fromKVs(Keyword::create('a'), $stored),
            Keyword::create('a'),
        ]];

        yield 'vector tail' => [static fn(mixed $stored): array => [
            $factory->persistentVectorFromArray([0, $stored, 2]),
            1,
        ]];

        yield 'vector root' => [static fn(mixed $stored): array => [
            $factory->persistentVectorFromArray([...range(0, 4), $stored, ...range(6, 99)]),
            5,
        ]];

        yield 'sub vector' => [static fn(mixed $stored): array => [
            $factory->persistentVectorFromArray([9, 0, $stored, 2])->cdr(),
            1,
        ]];
    }

    public static function provideTransientFlavours(): iterable
    {
        $factory = TypeFactory::getInstance();
        $key = Keyword::create('k');

        yield 'array map' => [static fn(mixed $stored): array => [
            $factory->persistentMapFromKVs($key, $stored)->asTransient(),
            $key,
        ]];

        yield 'hash map' => [static function (mixed $stored) use ($factory, $key): array {
            $kvs = [];
            for ($i = 0; $i < 90; ++$i) {
                $kvs[] = Keyword::create('k' . $i);
                $kvs[] = $i;
            }

            return [
                $factory->persistentMapFromArray([...$kvs, $key, $stored])->asTransient(),
                $key,
            ];
        }];

        yield 'hash map nil key' => [static fn(mixed $stored): array => [
            PersistentHashMap::empty($factory->getHasher(), $factory->getEqualizer())->put(1, 1)->put(null, $stored)->asTransient(),
            null,
        ]];

        yield 'sorted map' => [static fn(mixed $stored): array => [
            $factory->persistentSortedMapFromArray([$key, $stored])->asTransient(),
            $key,
        ]];
    }

    #[DataProvider('provideFlavours')]
    public function test_an_identical_value_returns_the_receiver(Closure $holding): void
    {
        $vector = TypeFactory::getInstance()->persistentVectorFromArray([1, 2]);

        foreach ([1, 'a', null, true, 1.5, 0.0, -0.0, Keyword::create('x'), $vector] as $value) {
            [$coll, $key] = $holding($value);

            self::assertSame($coll, $this->put($coll, $key, $value));
        }
    }

    #[DataProvider('provideFlavours')]
    public function test_a_different_value_returns_a_copy(Closure $holding): void
    {
        [$coll, $key] = $holding(1);

        $result = $this->put($coll, $key, 2);

        self::assertNotSame($coll, $result);
        self::assertSame(2, $this->find($result, $key));
        self::assertSame(1, $this->find($coll, $key));
        self::assertCount(count($coll), $result);
    }

    #[DataProvider('provideFlavours')]
    public function test_negative_zero_over_positive_zero_is_written(Closure $holding): void
    {
        [$coll, $key] = $holding(0.0);

        $result = $this->put($coll, $key, -0.0);

        self::assertNotSame($coll, $result);
        self::assertSame(-INF, fdiv(1.0, $this->find($result, $key)));
    }

    #[DataProvider('provideFlavours')]
    public function test_positive_zero_over_negative_zero_is_written(Closure $holding): void
    {
        [$coll, $key] = $holding(-0.0);

        $result = $this->put($coll, $key, 0.0);

        self::assertNotSame($coll, $result);
        self::assertSame(INF, fdiv(1.0, $this->find($result, $key)));
    }

    #[DataProvider('provideFlavours')]
    public function test_nan_over_nan_is_written(Closure $holding): void
    {
        [$coll, $key] = $holding(NAN);

        $result = $this->put($coll, $key, NAN);

        self::assertNotSame($coll, $result);
        self::assertNan($this->find($result, $key));
    }

    #[DataProvider('provideFlavours')]
    public function test_a_bigint_over_an_equal_int_is_written(Closure $holding): void
    {
        [$coll, $key] = $holding(1);
        $big = BigInt::fromInt(1);

        $result = $this->put($coll, $key, $big);

        self::assertSame($big, $this->find($result, $key));
    }

    #[DataProvider('provideFlavours')]
    public function test_an_equal_value_carrying_other_metadata_is_written(Closure $holding): void
    {
        $factory = TypeFactory::getInstance();
        [$coll, $key] = $holding($factory->persistentVectorFromArray([1, 2]));
        $withMeta = $factory->persistentVectorFromArray([1, 2])
            ->withMeta($factory->persistentMapFromKVs(Keyword::create('tag'), true));

        $result = $this->put($coll, $key, $withMeta);

        self::assertSame($withMeta, $this->find($result, $key));
    }

    #[DataProvider('provideFlavours')]
    public function test_the_receiver_keeps_its_metadata(Closure $holding): void
    {
        [$coll, $key] = $holding(1);
        $meta = TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('tag'), true);
        $coll = $coll->withMeta($meta);

        self::assertSame($meta, $this->put($coll, $key, 1)->getMeta());
        self::assertSame($meta, $this->put($coll, $key, 2)->getMeta());
    }

    #[DataProvider('provideTransientFlavours')]
    public function test_a_transient_writes_a_signed_zero_and_keeps_its_count(Closure $holding): void
    {
        /** @var TransientMapInterface<mixed, mixed> $transient */
        [$transient, $key] = $holding(0.0);
        $count = count($transient);

        $transient = $transient->put($key, 0.0);
        self::assertCount($count, $transient);

        $transient = $transient->put($key, -0.0);
        self::assertCount($count, $transient);
        self::assertSame(-INF, fdiv(1.0, $transient->find($key)));
        self::assertSame(-INF, fdiv(1.0, $transient->persistent()->find($key)));
    }

    #[DataProvider('provideFlavours')]
    public function test_a_recursive_array_over_an_equal_recursive_array_is_written(Closure $holding): void
    {
        [$stored, $new] = self::recursiveArrays();
        [$coll, $key] = $holding($stored);

        $result = $this->put($coll, $key, $new);

        self::assertNotSame($coll, $result);
        self::assertSame('new', $this->find($result, $key)[2]);
        self::assertSame('stored', $this->find($coll, $key)[2]);
    }

    #[DataProvider('provideFlavours')]
    public function test_an_array_holding_other_references_to_equal_values_is_written(Closure $holding): void
    {
        $x = 1;
        $y = 1;
        [$coll, $key] = $holding([&$x]);

        $result = $this->put($coll, $key, [&$y]);
        ++$y;

        self::assertNotSame($coll, $result);
        self::assertSame(2, $this->find($result, $key)[0]);
        self::assertSame(1, $this->find($coll, $key)[0]);
    }

    #[DataProvider('provideTransientFlavours')]
    public function test_a_transient_writes_a_recursive_array_over_an_equal_one(Closure $holding): void
    {
        [$stored, $new] = self::recursiveArrays();
        /** @var TransientMapInterface<mixed, mixed> $transient */
        [$transient, $key] = $holding($stored);

        $transient = $transient->put($key, $new);

        self::assertSame('new', $transient->find($key)[2]);
    }

    #[DataProvider('provideTransientFlavours')]
    public function test_a_transient_writes_an_array_holding_other_references(Closure $holding): void
    {
        $x = 1;
        $y = 1;
        /** @var TransientMapInterface<mixed, mixed> $transient */
        [$transient, $key] = $holding([&$x]);

        $transient = $transient->put($key, [&$y]);
        ++$y;

        self::assertSame(2, $transient->find($key)[0]);
    }

    /**
     * Two distinct arrays that `===` cannot compare: both recurse at index 1,
     * before the marker at index 2 that tells them apart.
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private static function recursiveArrays(): array
    {
        $stored = [1];
        $stored[] = &$stored;
        $stored[] = 'stored';
        $new = [1];
        $new[] = &$new;
        $new[] = 'new';

        return [$stored, $new];
    }

    private function put(mixed $coll, mixed $key, mixed $value): mixed
    {
        if ($coll instanceof PersistentVectorInterface) {
            return $coll->update($key, $value);
        }

        /** @var PersistentMapInterface<mixed, mixed> $coll */
        return $coll->put($key, $value);
    }

    private function find(mixed $coll, mixed $key): mixed
    {
        if ($coll instanceof PersistentVectorInterface) {
            return $coll->get($key);
        }

        /** @var PersistentMapInterface<mixed, mixed> $coll */
        return $coll->find($key);
    }
}
