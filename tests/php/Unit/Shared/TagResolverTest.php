<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Lang\Atom;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Shared\TagResolver;
use PHPUnit\Framework\TestCase;

final class TagResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        TagResolver::setUseAliasResolver(null);
    }

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

    public function test_dotted_class_tag_is_rooted_for_php(): void
    {
        // The leading backslash is the point: a generated file declares its own
        // namespace, so an unrooted type would resolve against it.
        $rooted = '\\' . Symbol::class;

        self::assertSame($rooted, TagResolver::normalizeScalar(Symbol::create('Phel.Lang.Symbol')));
        self::assertSame($rooted, TagResolver::normalizeScalar('Phel.Lang.Symbol'));
    }

    public function test_dotted_class_tag_keeps_its_nullable_marker(): void
    {
        self::assertSame('?\\My\\Ns\\Thing', TagResolver::normalizeScalar('?My.Ns.Thing'));
    }

    public function test_each_member_of_a_composite_tag_is_rooted(): void
    {
        self::assertSame('\\My\\Ns\\Thing|null', TagResolver::normalizeScalar('My.Ns.Thing|null'));
        self::assertSame('\\A\\B&\\C\\D', TagResolver::normalizeScalar('A.B&C.D'));
    }

    public function test_a_tag_without_a_dot_is_untouched(): void
    {
        self::assertSame('int|null', TagResolver::normalizeScalar('int|null'));
        self::assertSame('DateTime', TagResolver::normalizeScalar('DateTime'));
        self::assertSame('self', TagResolver::normalizeScalar('self'));
    }

    public function test_a_backslash_tag_is_untouched(): void
    {
        $rooted = '\\' . Symbol::class;

        self::assertSame($rooted, TagResolver::normalizeScalar($rooted));
    }

    public function test_collection_aliases_resolve_to_the_rooted_interfaces(): void
    {
        self::assertSame('\\' . PersistentMapInterface::class, TagResolver::normalizeScalar(Symbol::create('map')));
        self::assertSame('\\' . PersistentVectorInterface::class, TagResolver::normalizeScalar('vector'));
        self::assertSame('\\' . PersistentHashSetInterface::class, TagResolver::normalizeScalar('set'));
        self::assertSame('\\' . PersistentListInterface::class, TagResolver::normalizeScalar('list'));
    }

    public function test_value_type_aliases_resolve_to_their_classes(): void
    {
        self::assertSame('\\' . Keyword::class, TagResolver::normalizeScalar('keyword'));
        self::assertSame('\\' . Symbol::class, TagResolver::normalizeScalar(Symbol::create('symbol')));
        self::assertSame('\\' . Atom::class, TagResolver::normalizeScalar('atom'));
    }

    public function test_collection_alias_keeps_its_nullable_marker(): void
    {
        self::assertSame('?\\' . PersistentMapInterface::class, TagResolver::normalizeScalar('?map'));
    }

    public function test_collection_alias_applies_per_member_of_a_composite(): void
    {
        self::assertSame(
            '\\' . PersistentMapInterface::class . '|null',
            TagResolver::normalizeScalar('map|null'),
        );
        self::assertSame(
            '\\' . PersistentVectorInterface::class . '|\\My\\Ns\\Thing',
            TagResolver::normalizeScalar('vector|My.Ns.Thing'),
        );
    }

    public function test_scalar_and_unknown_bare_tags_are_untouched(): void
    {
        self::assertSame('int', TagResolver::normalizeScalar('int'));
        self::assertSame('array', TagResolver::normalizeScalar('array'));
        self::assertSame('DateTime', TagResolver::normalizeScalar('DateTime'));
    }

    public function test_a_use_alias_resolves_a_bare_class_name(): void
    {
        TagResolver::setUseAliasResolver(
            static fn(string $alias): ?string => $alias === 'Thing' ? '\\My\\Ns\\Thing' : null,
        );

        self::assertSame('\\My\\Ns\\Thing', TagResolver::normalizeScalar(Symbol::create('Thing')));
        self::assertSame('?\\My\\Ns\\Thing', TagResolver::normalizeScalar('?Thing'));
        self::assertSame('DateTime', TagResolver::normalizeScalar('DateTime'), 'a name the namespace never imported stays as written');
    }

    public function test_a_use_alias_wins_over_a_collection_alias(): void
    {
        TagResolver::setUseAliasResolver(static fn(string $alias): ?string => $alias === 'map' ? '\\My\\Map' : null);

        self::assertSame('\\My\\Map', TagResolver::normalizeScalar('map'));
        self::assertSame('\\' . PersistentVectorInterface::class, TagResolver::normalizeScalar('vector'));
    }

    public function test_a_qualified_tag_bypasses_the_use_alias_resolver(): void
    {
        TagResolver::setUseAliasResolver(static fn(string $alias): string => '\\Wrong\\Answer');

        self::assertSame('\\' . Symbol::class, TagResolver::normalizeScalar('Phel.Lang.Symbol'));
        self::assertSame('\\Already\\Rooted', TagResolver::normalizeScalar('\\Already\\Rooted'));
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
