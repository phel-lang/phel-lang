<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use InvalidArgumentException;
use Phel\Lang\UUID;
use PHPUnit\Framework\TestCase;

final class UUIDTest extends TestCase
{
    public function test_from_string_lowercases_canonical_input(): void
    {
        $uuid = UUID::fromString('550E8400-E29B-41D4-A716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid);
    }

    public function test_from_string_accepts_lowercase(): void
    {
        $uuid = UUID::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid);
    }

    public function test_from_string_rejects_short_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UUID::fromString('550e8400-e29b-41d4-a716-44665544000');
    }

    public function test_from_string_rejects_non_hex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UUID::fromString('zzzzzzzz-e29b-41d4-a716-446655440000');
    }

    public function test_from_string_rejects_no_dashes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UUID::fromString('550e8400e29b41d4a716446655440000');
    }

    public function test_random_v4_matches_canonical_shape(): void
    {
        $uuid = UUID::randomV4();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $uuid,
        );
    }

    public function test_random_v4_returns_distinct_values(): void
    {
        self::assertNotSame((string) UUID::randomV4(), (string) UUID::randomV4());
    }

    public function test_version_reads_v4(): void
    {
        self::assertSame(4, UUID::fromString('550e8400-e29b-41d4-a716-446655440000')->version());
    }

    public function test_version_reads_v1(): void
    {
        self::assertSame(1, UUID::fromString('00000000-0000-1000-8000-000000000000')->version());
    }

    public function test_variant_rfc_4122(): void
    {
        self::assertSame('rfc-4122', UUID::fromString('550e8400-e29b-41d4-a716-446655440000')->variant());
    }

    public function test_variant_ncs_for_nil_uuid(): void
    {
        self::assertSame('ncs', UUID::fromString('00000000-0000-0000-0000-000000000000')->variant());
    }

    public function test_variant_microsoft(): void
    {
        self::assertSame('microsoft', UUID::fromString('00000000-0000-0000-c000-000000000000')->variant());
    }

    public function test_variant_reserved(): void
    {
        self::assertSame('reserved', UUID::fromString('00000000-0000-0000-e000-000000000000')->variant());
    }

    public function test_is_nil_for_nil_uuid(): void
    {
        self::assertTrue(UUID::fromString('00000000-0000-0000-0000-000000000000')->isNil());
    }

    public function test_is_nil_false_for_non_nil(): void
    {
        self::assertFalse(UUID::fromString('550e8400-e29b-41d4-a716-446655440000')->isNil());
    }

    public function test_equals_same_canonical_value(): void
    {
        $a = UUID::fromString('550e8400-e29b-41d4-a716-446655440000');
        $b = UUID::fromString('550E8400-E29B-41D4-A716-446655440000');

        self::assertTrue($a->equals($b));
        self::assertSame($a->hash(), $b->hash());
    }

    public function test_equals_false_for_different_canonical(): void
    {
        $a = UUID::fromString('550e8400-e29b-41d4-a716-446655440000');
        $b = UUID::fromString('00000000-0000-0000-0000-000000000000');

        self::assertFalse($a->equals($b));
    }

    public function test_equals_false_for_string_with_same_value(): void
    {
        $uuid = UUID::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertFalse($uuid->equals('550e8400-e29b-41d4-a716-446655440000'));
    }
}
