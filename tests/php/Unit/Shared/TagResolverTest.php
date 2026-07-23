<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Shared\TagResolver;
use PHPUnit\Framework\TestCase;

final class TagResolverTest extends TestCase
{
    public function test_from_meta_null_meta_is_null(): void
    {
        self::assertNull(TagResolver::fromMeta(null));
    }

    public function test_from_meta_without_tag_key_is_null(): void
    {
        self::assertNull(TagResolver::fromMeta($this->meta()));
    }

    public function test_from_meta_string_tag_passes_through(): void
    {
        self::assertSame('int', TagResolver::fromMeta($this->tagMeta('int')));
    }

    public function test_from_meta_symbol_tag_resolves_to_its_name(): void
    {
        self::assertSame('DateTime', TagResolver::fromMeta($this->tagMeta(Symbol::create('DateTime'))));
    }

    public function test_from_meta_empty_string_tag_is_null(): void
    {
        self::assertNull(TagResolver::fromMeta($this->tagMeta('')));
    }

    public function test_normalize_scalar_symbol_yields_name(): void
    {
        self::assertSame('foo', TagResolver::normalizeScalar(Symbol::create('foo')));
    }

    public function test_normalize_scalar_non_empty_string_passes_through(): void
    {
        self::assertSame('?int', TagResolver::normalizeScalar('?int'));
    }

    public function test_normalize_scalar_empty_string_is_null(): void
    {
        self::assertNull(TagResolver::normalizeScalar(''));
    }

    public function test_normalize_scalar_non_string_is_null(): void
    {
        self::assertNull(TagResolver::normalizeScalar(42));
    }

    public function test_normalize_scalar_null_is_null(): void
    {
        self::assertNull(TagResolver::normalizeScalar(null));
    }

    private function tagMeta(mixed $value): PersistentMapInterface
    {
        return $this->meta(Keyword::create('tag'), $value);
    }

    private function meta(mixed ...$kvs): PersistentMapInterface
    {
        return TypeFactory::getInstance()->persistentMapFromKVs(...$kvs);
    }
}
