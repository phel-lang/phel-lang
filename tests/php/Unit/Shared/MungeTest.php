<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\Munge;
use PHPUnit\Framework\TestCase;

final class MungeTest extends TestCase
{
    private Munge $munge;

    protected function setUp(): void
    {
        $this->munge = new Munge();
    }

    public function test_encode_leaves_plain_identifier_untouched(): void
    {
        self::assertSame('foo', $this->munge->encode('foo'));
    }

    public function test_encode_special_cases_this(): void
    {
        self::assertSame('__phel_this', $this->munge->encode('this'));
    }

    public function test_encode_replaces_dash_with_underscore(): void
    {
        self::assertSame('my_function', $this->munge->encode('my-function'));
    }

    public function test_encode_replaces_special_characters(): void
    {
        self::assertSame('foo_QMARK_', $this->munge->encode('foo?'));
        self::assertSame('foo_BANG_', $this->munge->encode('foo!'));
        self::assertSame('_PLUS_', $this->munge->encode('+'));
        self::assertSame('_GT_', $this->munge->encode('>'));
        self::assertSame('_LT_', $this->munge->encode('<'));
        self::assertSame('_EQ_', $this->munge->encode('='));
        self::assertSame('_STAR_', $this->munge->encode('*'));
        self::assertSame('_SLASH_', $this->munge->encode('/'));
        self::assertSame('_DOT_', $this->munge->encode('.'));
    }

    public function test_encode_php_ns_replaces_dot_with_backslash(): void
    {
        self::assertSame('phel\\core', $this->munge->encodePhpNs('phel.core'));
    }

    public function test_encode_php_ns_replaces_dash_with_underscore(): void
    {
        self::assertSame('my_app\\sub_ns', $this->munge->encodePhpNs('my-app.sub-ns'));
    }

    public function test_encode_registry_key_uses_dot_form(): void
    {
        // Registry key canonicalises to dot form; the namespace mapping only
        // rewrites '-' so dots are preserved.
        self::assertSame('phel.core', $this->munge->encodeRegistryKey('phel\\core'));
        self::assertSame('phel.core', $this->munge->encodeRegistryKey('phel.core'));
    }

    public function test_encode_registry_key_replaces_dash(): void
    {
        self::assertSame('my_app', $this->munge->encodeRegistryKey('my-app'));
    }

    public function test_decode_ns_is_inverse_of_namespace_encoding(): void
    {
        self::assertSame('my-app', $this->munge->decodeNs('my_app'));
    }

    public function test_canonical_ns_converts_backslash_to_dot(): void
    {
        self::assertSame('phel.core', Munge::canonicalNs('phel\\core'));
        self::assertSame('a.b.c', Munge::canonicalNs('a\\b\\c'));
    }

    public function test_canonical_ns_leaves_dot_form_untouched(): void
    {
        self::assertSame('phel.core', Munge::canonicalNs('phel.core'));
    }

    public function test_display_ns_equals_canonical_ns(): void
    {
        self::assertSame('phel.core', Munge::displayNs('phel\\core'));
        self::assertSame(Munge::canonicalNs('a\\b'), Munge::displayNs('a\\b'));
    }

    public function test_custom_mapping_injection(): void
    {
        $munge = new Munge(['a' => 'X'], ['b' => 'Y']);

        self::assertSame('Xbc', $munge->encode('abc'));
        self::assertSame('aYc', $munge->encodeRegistryKey('abc'));
    }
}
