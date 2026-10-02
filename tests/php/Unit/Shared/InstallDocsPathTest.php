<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\InstallDocsPath;
use PHPUnit\Framework\TestCase;

final class InstallDocsPathTest extends TestCase
{
    private const string TEXT = 'The full table is in docs/migration/deprecated-surface.md.';

    public function test_a_checkout_keeps_the_path(): void
    {
        self::assertSame(self::TEXT, InstallDocsPath::rewrite(self::TEXT, '/app', '/app'));
    }

    public function test_a_vendor_install_names_the_path_from_the_project(): void
    {
        self::assertSame(
            'The full table is in vendor/phel-lang/phel-lang/docs/migration/deprecated-surface.md.',
            InstallDocsPath::rewrite(self::TEXT, '/app', '/app/vendor/phel-lang/phel-lang'),
        );
    }

    public function test_an_install_outside_the_project_is_named_absolutely(): void
    {
        self::assertSame(
            'The full table is in /opt/phel/docs/migration/deprecated-surface.md.',
            InstallDocsPath::rewrite(self::TEXT, '/app', '/opt/phel'),
        );
    }

    public function test_the_phar_keeps_the_path(): void
    {
        self::assertSame(self::TEXT, InstallDocsPath::rewrite(self::TEXT, '/app', 'phar:///usr/bin/phel.phar'));
    }

    public function test_only_a_path_that_starts_with_docs_is_rewritten(): void
    {
        self::assertSame(
            'See my-docs/x and https://example.org/docs/y.',
            InstallDocsPath::rewrite('See my-docs/x and https://example.org/docs/y.', '/app', '/opt/phel'),
        );
    }
}
