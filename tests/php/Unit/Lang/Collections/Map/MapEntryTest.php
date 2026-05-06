<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\MapEntry;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class MapEntryTest extends TestCase
{
    public function test_create_exposes_key_and_value(): void
    {
        $entry = MapEntry::create('k', 1);

        self::assertSame('k', $entry->key());
        self::assertSame(1, $entry->value());
    }

    public function test_count_is_always_two(): void
    {
        self::assertCount(2, MapEntry::create('k', 'v'));
    }

    public function test_iterates_key_then_value(): void
    {
        $entry = MapEntry::create('k', 1);
        $items = [];
        foreach ($entry as $item) {
            $items[] = $item;
        }

        self::assertSame(['k', 1], $items);
    }

    public function test_to_string_renders_two_element_vector(): void
    {
        self::assertSame('["k" 1]', (string) MapEntry::create('k', 1));
        self::assertSame('[1 nil]', (string) MapEntry::create(1, null));
    }

    public function test_equals_other_map_entry_with_same_pair(): void
    {
        $a = MapEntry::create('k', 1);
        $b = MapEntry::create('k', 1);

        self::assertTrue($a->equals($b));
        self::assertSame($a->hash(), $b->hash());
    }

    public function test_equals_two_element_vector_with_same_values(): void
    {
        $entry = MapEntry::create('k', 1);
        $vector = TypeFactory::getInstance()->persistentVectorFromArray(['k', 1]);

        self::assertTrue($entry->equals($vector));
        self::assertSame($entry->hash(), $vector->hash());
    }

    public function test_equals_false_for_different_pair(): void
    {
        $a = MapEntry::create('k', 1);
        $b = MapEntry::create('k', 2);

        self::assertFalse($a->equals($b));
    }

    public function test_equals_false_for_three_element_vector(): void
    {
        $entry = MapEntry::create('k', 1);
        $vector = TypeFactory::getInstance()->persistentVectorFromArray(['k', 1, 'extra']);

        self::assertFalse($entry->equals($vector));
    }

    public function test_equals_false_for_unrelated_value(): void
    {
        self::assertFalse(MapEntry::create('k', 1)->equals('k'));
    }
}
