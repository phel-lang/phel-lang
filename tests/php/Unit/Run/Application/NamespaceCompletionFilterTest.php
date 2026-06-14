<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Run\Application\NamespaceCompletionFilter;
use PHPUnit\Framework\TestCase;

final class NamespaceCompletionFilterTest extends TestCase
{
    public function test_empty_typed_value_returns_all_namespaces(): void
    {
        $namespaces = ['app.main', 'app.web', 'phel.core'];

        self::assertSame($namespaces, NamespaceCompletionFilter::matching($namespaces, ''));
    }

    public function test_filters_to_namespaces_containing_the_typed_value(): void
    {
        $namespaces = ['app.main', 'app.web', 'phel.core'];

        self::assertSame(['app.main', 'app.web'], NamespaceCompletionFilter::matching($namespaces, 'app'));
    }

    public function test_matches_anywhere_in_the_namespace_not_only_the_prefix(): void
    {
        $namespaces = ['app.web', 'phel.web-server', 'app.cli'];

        self::assertSame(['app.web', 'phel.web-server'], NamespaceCompletionFilter::matching($namespaces, 'web'));
    }

    public function test_returns_a_reindexed_list_when_filtering_drops_entries(): void
    {
        $namespaces = ['app.main', 'phel.core', 'app.web'];

        self::assertSame(['phel.core'], NamespaceCompletionFilter::matching($namespaces, 'phel'));
    }
}
