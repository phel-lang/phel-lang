<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\SymbolKey;
use PHPUnit\Framework\TestCase;

final class SymbolKeyTest extends TestCase
{
    public function test_qualified_symbol_is_returned_as_is(): void
    {
        self::assertSame('my-ns/foo', SymbolKey::resolve('ignored', 'my-ns/foo'));
    }

    public function test_unqualified_symbol_is_anchored_to_namespace(): void
    {
        self::assertSame('my-ns/foo', SymbolKey::resolve('my-ns', 'foo'));
    }

    public function test_empty_namespace_yields_plain_symbol(): void
    {
        self::assertSame('foo', SymbolKey::resolve('', 'foo'));
    }

    public function test_already_qualified_with_empty_namespace_passes_through(): void
    {
        self::assertSame('other/bar', SymbolKey::resolve('', 'other/bar'));
    }
}
