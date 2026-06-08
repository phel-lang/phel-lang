<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\VersionFinder;
use Phel\Shared\VersionResolver;
use PHPUnit\Framework\TestCase;

final class VersionResolverTest extends TestCase
{
    public function test_resolve_returns_a_version_string_rooted_at_the_latest_tag(): void
    {
        $version = new VersionResolver()->resolve();

        // Either the official tag, or `<tag>-beta#<hash>` in a dev checkout —
        // both start with the latest version.
        self::assertNotEmpty($version);
        self::assertStringStartsWith(
            VersionFinder::LATEST_VERSION,
            $version,
            'Resolved version should be rooted at the latest tag',
        );
    }
}
