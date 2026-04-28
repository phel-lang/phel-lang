<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpSymbolCatalog;
use PHPUnit\Framework\TestCase;

final class PhpSymbolCatalogTest extends TestCase
{
    public function test_functions_contains_internal_php_functions(): void
    {
        $catalog = new PhpSymbolCatalog();

        $functions = $catalog->functions();

        self::assertContains('array_map', $functions);
        self::assertContains('strlen', $functions);
    }

    public function test_classes_contains_standard_library_classes(): void
    {
        $catalog = new PhpSymbolCatalog();

        $classes = $catalog->classes();

        self::assertContains('stdClass', $classes);
    }

    public function test_functions_list_is_memoised(): void
    {
        $catalog = new PhpSymbolCatalog();

        self::assertSame($catalog->functions(), $catalog->functions());
    }

    public function test_classes_list_is_memoised(): void
    {
        $catalog = new PhpSymbolCatalog();

        self::assertSame($catalog->classes(), $catalog->classes());
    }
}
