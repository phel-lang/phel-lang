<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Struct;

use Phel\Compiler\Application\Munge;
use Phel\Lang\Collections\Struct\StructKeyEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StructKeyEncoderTest extends TestCase
{
    private const array MUNGED_CHARS = [
        '-', '.', ':', '+', '>', '<', '=', '~', '!', '@', '#',
        "'", '"', '%', '^', '&', '*', '|', '{', '}', '[', ']',
        '/', '\\', '?', '$',
    ];

    public function test_encodes_special_marker_for_this(): void
    {
        self::assertSame('__phel_this', (new StructKeyEncoder())->encode('this'));
    }

    public function test_passthrough_for_plain_identifiers(): void
    {
        $encoder = new StructKeyEncoder();
        self::assertSame('foo', $encoder->encode('foo'));
        self::assertSame('foo123', $encoder->encode('foo123'));
    }

    /**
     * Pin the encoding to Munge so any future drift breaks here. The mapping
     * is part of the on-disk format because defstruct emits property names via
     * Munge at compile time, then the runtime resolves them via this encoder.
     */
    #[DataProvider('provideMungedCharacters')]
    public function test_matches_munge_encode_for_each_special_char(string $char): void
    {
        $munge = new Munge();
        $encoder = new StructKeyEncoder();

        $name = 'a' . $char . 'b';
        self::assertSame($munge->encode($name), $encoder->encode($name));
    }

    public function test_matches_munge_for_combined_input(): void
    {
        $munge = new Munge();
        $encoder = new StructKeyEncoder();

        $name = implode('', self::MUNGED_CHARS);
        self::assertSame($munge->encode($name), $encoder->encode($name));
    }

    public static function provideMungedCharacters(): iterable
    {
        foreach (self::MUNGED_CHARS as $char) {
            yield $char => [$char];
        }
    }
}
