<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Application;

use Phel\Compiler\Application\Munge;
use PHPUnit\Framework\TestCase;

final class MungeTest extends TestCase
{
    private Munge $munge;

    protected function setUp(): void
    {
        $this->munge = new Munge();
    }

    public function test_canonical_ns_translates_backslash_to_dot(): void
    {
        self::assertSame('phel.core', Munge::canonicalNs('phel\\core'));
        self::assertSame('a.b.c', Munge::canonicalNs('a\\b\\c'));
    }

    public function test_canonical_ns_keeps_already_canonical_form(): void
    {
        self::assertSame('phel.core', Munge::canonicalNs('phel.core'));
    }

    public function test_canonical_ns_handles_empty_string(): void
    {
        self::assertSame('', Munge::canonicalNs(''));
    }

    public function test_encode_php_ns_produces_backslash_form(): void
    {
        self::assertSame('phel\\core', $this->munge->encodePhpNs('phel.core'));
        self::assertSame('phel\\core', $this->munge->encodePhpNs('phel\\core'));
    }

    public function test_encode_php_ns_munges_hyphens_after_canonicalizing(): void
    {
        self::assertSame('my_app\\my_module', $this->munge->encodePhpNs('my-app.my-module'));
    }

    public function test_encode_registry_key_produces_dot_form(): void
    {
        self::assertSame('phel.core', $this->munge->encodeRegistryKey('phel.core'));
        self::assertSame('phel.core', $this->munge->encodeRegistryKey('phel\\core'));
    }

    public function test_encode_registry_key_munges_hyphens_after_canonicalizing(): void
    {
        self::assertSame('my_app.my_module', $this->munge->encodeRegistryKey('my-app.my-module'));
        self::assertSame('my_app.my_module', $this->munge->encodeRegistryKey('my-app\\my-module'));
    }

    public function test_display_ns_translates_backslash_to_dot(): void
    {
        self::assertSame('phel.core', Munge::displayNs('phel\\core'));
        self::assertSame('a.b.c', Munge::displayNs('a\\b\\c'));
    }

    public function test_display_ns_keeps_already_display_form(): void
    {
        self::assertSame('phel.core', Munge::displayNs('phel.core'));
    }

    public function test_display_ns_handles_empty_string(): void
    {
        self::assertSame('', Munge::displayNs(''));
    }

    public function test_canonical_and_display_round_trip(): void
    {
        self::assertSame('phel.core', Munge::displayNs(Munge::canonicalNs('phel.core')));
        self::assertSame('phel.core', Munge::canonicalNs(Munge::displayNs('phel\\core')));
    }
}
