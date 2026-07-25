<?php

declare(strict_types=1);

namespace PhelTest\Unit\Phel;

use Phel;
use PHPUnit\Framework\TestCase;

/**
 * The registry keys namespaces in munged form (`my_app.lib`), while everything
 * above the emitter speaks the canonical Phel form (`my-app.lib`). Comparing
 * the two directly made every kebab-case namespace look unloaded, so each
 * `(:require ...)` of one re-evaluated its file and re-ran its top level.
 */
final class PhelNamespaceLoadedTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::clear();
    }

    protected function tearDown(): void
    {
        Phel::clear();
    }

    public function test_finds_a_kebab_case_namespace_by_its_canonical_name(): void
    {
        Phel::registerNamespace('my_app.cross_require.lib');

        self::assertTrue(Phel::isNamespaceLoaded('my-app.cross-require.lib'));
    }

    public function test_finds_a_namespace_written_with_the_legacy_backslash_separator(): void
    {
        Phel::registerNamespace('my_app.cross_require.lib');

        self::assertTrue(Phel::isNamespaceLoaded('my-app\\cross-require\\lib'));
    }

    public function test_finds_a_plain_namespace(): void
    {
        Phel::registerNamespace('plain.lib');

        self::assertTrue(Phel::isNamespaceLoaded('plain.lib'));
    }

    public function test_reports_an_unloaded_namespace_as_unloaded(): void
    {
        self::assertFalse(Phel::isNamespaceLoaded('never.loaded'));
    }
}
