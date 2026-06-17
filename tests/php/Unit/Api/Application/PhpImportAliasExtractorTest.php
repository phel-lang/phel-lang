<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpImportAliasExtractor;
use PHPUnit\Framework\TestCase;

final class PhpImportAliasExtractorTest extends TestCase
{
    private PhpImportAliasExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new PhpImportAliasExtractor();
    }

    public function test_ns_use_maps_last_segment_to_fqn(): void
    {
        $aliases = $this->extractor->extract('(ns app (:use Some\\Long\\Widget))');

        self::assertSame(['Widget' => 'Some\\Long\\Widget'], $aliases);
    }

    public function test_top_level_use_is_collected(): void
    {
        $aliases = $this->extractor->extract('(use Some\\Long\\Widget)');

        self::assertSame(['Widget' => 'Some\\Long\\Widget'], $aliases);
    }

    public function test_as_alias_overrides_the_short_name(): void
    {
        $aliases = $this->extractor->extract('(ns app (:use Some\\Long\\Widget :as W))');

        self::assertSame(['W' => 'Some\\Long\\Widget'], $aliases);
    }

    public function test_multiple_imports_in_one_clause(): void
    {
        $aliases = $this->extractor->extract('(ns app (:use A\\Foo B\\Bar :as Baz))');

        self::assertSame(['Foo' => 'A\\Foo', 'Baz' => 'B\\Bar'], $aliases);
    }

    public function test_dot_separator_is_normalised_to_backslash(): void
    {
        $aliases = $this->extractor->extract('(use Some.Long.Widget)');

        self::assertSame(['Widget' => 'Some\\Long\\Widget'], $aliases);
    }

    public function test_source_without_imports_yields_empty_map(): void
    {
        self::assertSame([], $this->extractor->extract('(defn foo [x] (inc x))'));
    }

    public function test_use_prefixed_symbol_is_not_mistaken_for_an_import(): void
    {
        self::assertSame([], $this->extractor->extract('(use-fixtures :each setup)'));
    }
}
