<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\VersionFinder;
use PHPUnit\Framework\TestCase;

final class VersionFinderTest extends TestCase
{
    public function test_official_release_returns_latest_version(): void
    {
        $finder = new VersionFinder('abc1234abc1234abc1234abc1234abc1234abc1', 'def5678', true);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_empty_current_commit_returns_latest_version(): void
    {
        $finder = new VersionFinder('abc1234', '', false);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_current_commit_equal_to_tag_returns_latest_version(): void
    {
        $finder = new VersionFinder('abc1234', 'abc1234', false);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_valid_commit_hash_returns_beta_with_short_hash(): void
    {
        $finder = new VersionFinder('tag1234', 'abcdef1234567890', false);

        self::assertSame(VersionFinder::LATEST_VERSION . '-beta#abcdef1', $finder->getVersion());
    }

    public function test_full_40_char_sha_is_truncated_to_seven(): void
    {
        $sha = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';
        $finder = new VersionFinder('tag', $sha, false);

        self::assertSame(VersionFinder::LATEST_VERSION . '-beta#a1b2c3d', $finder->getVersion());
    }

    public function test_too_short_hash_falls_back_to_latest_version(): void
    {
        // 7 chars is below the 8-char minimum of the SHA pattern.
        $finder = new VersionFinder('tag', 'abc1234', false);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_non_hex_commit_falls_back_to_latest_version(): void
    {
        $finder = new VersionFinder('tag', 'zzzzzzzz', false);

        self::assertSame(VersionFinder::LATEST_VERSION, $finder->getVersion());
    }

    public function test_whitespace_padded_hash_is_trimmed_and_valid(): void
    {
        $finder = new VersionFinder('tag', '  abcdef12  ', false);

        self::assertSame(VersionFinder::LATEST_VERSION . '-beta#abcdef1', $finder->getVersion());
    }

    public function test_version_is_cached_across_calls(): void
    {
        $finder = new VersionFinder('tag', 'abcdef1234567890', false);

        self::assertSame($finder->getVersion(), $finder->getVersion());
    }
}
