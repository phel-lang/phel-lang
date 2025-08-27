<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Application;

use Phel\Console\Application\VersionFinder;
use PHPUnit\Framework\TestCase;

final class VersionFinderTest extends TestCase
{
    public function test_returns_latest_version_when_commits_match(): void
    {
        $commit = '123456abcdef';
        $finder = new VersionFinder(
            $commit,
            $commit,
        );

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_returns_beta_version_when_commits_differ(): void
    {
        $finder = new VersionFinder(
            'abcdef123456',
            '1234567890abcdef',
        );

        self::assertSame(
            VersionFinder::LATEST_VERSION . '-beta#1234567',
            $finder->getVersion(),
        );
    }

    public function test_returns_latest_version_when_current_commit_empty(): void
    {
        $finder = new VersionFinder(
            'abcdef123456',
            '',
        );

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_returns_latest_version_when_tag_commit_empty(): void
    {
        $finder = new VersionFinder(
            '',
            '1234567890abcdef',
        );

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_returns_latest_version_when_current_commit_invalid(): void
    {
        $finder = new VersionFinder(
            'abcdef123456',
            'not-a-sha',
        );

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_caches_computed_version(): void
    {
        $finder = new VersionFinder(
            'abcdef123456',
            '1234567890abcdef',
        );

        $first = $finder->getVersion();
        $second = $finder->getVersion();

        self::assertSame($first, $second, 'Version should be cached');
    }

    public function test_accepts_full_40_char_git_hash(): void
    {
        $hash = str_repeat('a', 40);
        $finder = new VersionFinder(
            'bbbbbb',
            $hash,
        );

        self::assertSame(
            VersionFinder::LATEST_VERSION . '-beta#aaaaaaa',
            $finder->getVersion(),
        );
    }
}
