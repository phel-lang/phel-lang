<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel;
use Phel\Lang\GetIn;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use PHPUnit\Framework\TestCase;

/**
 * `GetIn::path` is what a `(get-in ds [k1 k2 …])` with a literal path
 * compiles to (#3320), so every answer here must be the one the runtime
 * `phel.core/get-in` gives. `tests/phel/core/get-in-literal-path.phel`
 * compares the two directly with the core loaded.
 */
final class GetInTest extends TestCase
{
    /** @var array{definitions: array<string, array<string, mixed>>, definitionsMetaData: array<string, array<string, mixed>>} */
    private array $registrySnapshot;

    protected function setUp(): void
    {
        $this->registrySnapshot = Registry::getInstance()->snapshot();
    }

    protected function tearDown(): void
    {
        Registry::getInstance()->restore($this->registrySnapshot);
    }

    public function test_walks_nested_maps(): void
    {
        $m = Phel::map(Keyword::create('a'), Phel::map(Keyword::create('b'), 42));

        self::assertSame(42, GetIn::path($m, [Keyword::create('a'), Keyword::create('b')]));
    }

    public function test_walks_vectors_with_integer_keys(): void
    {
        $v = Phel::vector([Phel::vector([10, 20])]);

        self::assertSame(20, GetIn::path($v, [0, 1]));
    }

    public function test_a_non_integer_key_on_a_vector_is_a_miss(): void
    {
        $v = Phel::vector([10, 20]);

        self::assertNull(GetIn::path($v, ['0']));
        self::assertSame('nf', GetIn::path($v, [Keyword::create('a')], 'nf'));
    }

    public function test_an_out_of_bounds_index_is_a_miss(): void
    {
        self::assertSame('nf', GetIn::path(Phel::vector([1]), [5], 'nf'));
    }

    public function test_a_missing_key_answers_the_default(): void
    {
        $m = Phel::map(Keyword::create('a'), Phel::map());

        self::assertNull(GetIn::path($m, [Keyword::create('a'), Keyword::create('b')]));
        self::assertSame('nf', GetIn::path($m, [Keyword::create('a'), Keyword::create('b')], 'nf'));
        self::assertSame('nf', GetIn::path($m, [Keyword::create('z'), Keyword::create('b')], 'nf'));
    }

    public function test_a_stored_nil_answers_the_default(): void
    {
        $m = Phel::map(Keyword::create('a'), null);

        self::assertSame('nf', GetIn::path($m, [Keyword::create('a')], 'nf'));
    }

    public function test_stored_false_is_a_value_not_a_miss(): void
    {
        $m = Phel::map(Keyword::create('a'), false);

        self::assertFalse(GetIn::path($m, [Keyword::create('a')], 'nf'));
    }

    public function test_a_nil_target_answers_the_default(): void
    {
        self::assertNull(GetIn::path(null, [Keyword::create('a')]));
        self::assertSame('nf', GetIn::path(null, [Keyword::create('a')], 'nf'));
    }

    public function test_an_empty_path_answers_the_target_as_is(): void
    {
        $m = Phel::map();

        self::assertSame($m, GetIn::path($m, [], 'nf'));
        self::assertNull(GetIn::path(null, [], 'nf'));
    }

    public function test_a_non_traversable_level_answers_the_default(): void
    {
        $m = Phel::map(Keyword::create('a'), 1);

        self::assertSame('nf', GetIn::path($m, [Keyword::create('a'), Keyword::create('b')], 'nf'));
        self::assertSame('nf', GetIn::path(Keyword::create('a'), [Keyword::create('b')], 'nf'));
    }

    /**
     * A transient map is not `coll?`, so the runtime stops at it even though
     * `get` itself would read it.
     */
    public function test_a_transient_map_is_not_traversed(): void
    {
        $t = Phel::map(Keyword::create('a'), 1)->asTransient();

        self::assertSame('nf', GetIn::path($t, [Keyword::create('a')], 'nf'));
    }

    /**
     * Anything `coll?`, a PHP array or a string that is not a map or a vector
     * is answered by `phel.core/get`, read from the registry at call time.
     */
    public function test_other_traversables_delegate_to_core_get(): void
    {
        Registry::getInstance()->addDefinition(
            'phel.core',
            'get',
            static fn(mixed $ds, mixed $k): string => 'get:' . $k,
        );

        self::assertSame('get:0', GetIn::path(['x'], [0]));
        self::assertSame('get:1', GetIn::path('abc', [1]));
        self::assertSame('get:2', GetIn::path(Phel::set([1, 2]), [2]));
        self::assertSame('get:3', GetIn::path(Phel::list([1]), [3]));
    }
}
