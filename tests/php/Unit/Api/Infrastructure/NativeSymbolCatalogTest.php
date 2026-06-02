<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Infrastructure;

use Phel\Api\Infrastructure\NativeSymbolCatalog;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class NativeSymbolCatalogTest extends TestCase
{
    public function test_definitions_is_non_empty(): void
    {
        self::assertNotSame([], NativeSymbolCatalog::definitions());
    }

    public function test_definitions_include_special_forms_and_builtins(): void
    {
        $definitions = NativeSymbolCatalog::definitions();

        foreach ([Symbol::NAME_IF, Symbol::NAME_FN, Symbol::NAME_DEF, '*file*', '*ns*'] as $symbol) {
            self::assertArrayHasKey($symbol, $definitions);
        }
    }

    public function test_every_entry_exposes_signatures(): void
    {
        foreach (NativeSymbolCatalog::definitions() as $symbol => $meta) {
            self::assertArrayHasKey('signatures', $meta, sprintf('Entry "%s" should declare signatures', $symbol));
            self::assertNotSame([], $meta['signatures']);
        }
    }
}
