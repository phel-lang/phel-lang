<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Application;

use Phel\Console\Application\VersionFinder;
use PHPUnit\Framework\TestCase;

final class VersionFinderTest extends TestCase
{
    public function test_returns_latest_version_when_tag_matches(): void
    {
        $finder = new VersionFinder([
            'pretty_version' => VersionFinder::LATEST_VERSION,
        ]);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_returns_beta_version_when_not_latest_and_reference_provided(): void
    {
        $finder = new VersionFinder([
            'pretty_version' => 'v0.19.0',
            'reference' => '1234567890abcdef',
        ]);

        self::assertSame(
            VersionFinder::LATEST_VERSION . '-beta#1234567',
            $finder->getVersion(),
        );
    }

    public function test_returns_latest_version_when_no_reference_provided(): void
    {
        $finder = new VersionFinder([
            'pretty_version' => 'v0.19.0',
        ]);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_returns_latest_version_when_reference_is_empty(): void
    {
        $finder = new VersionFinder([
            'pretty_version' => 'v0.18.0',
            'reference' => '',
        ]);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_returns_latest_version_when_reference_is_invalid(): void
    {
        $finder = new VersionFinder([
            'pretty_version' => 'v0.18.0',
            'reference' => 'not-a-sha',
        ]);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_caches_computed_version(): void
    {
        $finder = new VersionFinder([
            'pretty_version' => 'v0.18.0',
            'reference' => 'abcdef123456',
        ]);

        $first = $finder->getVersion();
        $second = $finder->getVersion();

        self::assertSame($first, $second, 'Version should be cached');
    }

    public function test_accepts_full_40_char_git_hash(): void
    {
        $hash = str_repeat('a', 40);
        $finder = new VersionFinder([
            'pretty_version' => 'v0.10.0',
            'reference' => $hash,
        ]);

        self::assertSame(
            VersionFinder::LATEST_VERSION . '-beta#aaaaaaa',
            $finder->getVersion(),
        );
    }

    public function test_does_not_append_beta_when_tag_is_latest_even_if_reference_provided(): void
    {
        $finder = new VersionFinder([
            'pretty_version' => VersionFinder::LATEST_VERSION,
            'reference' => '1234567890abcdef',
        ]);

        self::assertSame(
            VersionFinder::LATEST_VERSION,
            $finder->getVersion(),
            'Beta suffix must not be appended when tag equals LATEST_VERSION',
        );
    }
}
