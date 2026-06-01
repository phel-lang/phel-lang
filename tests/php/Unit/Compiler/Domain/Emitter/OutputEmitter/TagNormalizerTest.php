<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Emitter\OutputEmitter\TagNormalizer;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class TagNormalizerTest extends TestCase
{
    public function test_null_tag_stays_null(): void
    {
        self::assertNull(TagNormalizer::normalise(null));
    }

    public function test_leading_backslash_is_stripped(): void
    {
        self::assertSame(Symbol::class, TagNormalizer::normalise('\\' . Symbol::class));
    }

    public function test_tag_without_leading_backslash_is_unchanged(): void
    {
        self::assertSame('int', TagNormalizer::normalise('int'));
    }

    public function test_is_persistent_map_matches_with_or_without_leading_backslash(): void
    {
        self::assertTrue(TagNormalizer::isPersistentMap(PersistentMapInterface::class));
        self::assertTrue(TagNormalizer::isPersistentMap('\\' . PersistentMapInterface::class));
    }

    public function test_is_persistent_map_rejects_other_tags(): void
    {
        self::assertFalse(TagNormalizer::isPersistentMap('int'));
        self::assertFalse(TagNormalizer::isPersistentMap(null));
    }
}
