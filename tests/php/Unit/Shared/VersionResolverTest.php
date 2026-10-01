<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\VersionFinder;
use Phel\Shared\VersionResolver;
use PHPUnit\Framework\TestCase;

use function chdir;
use function exec;
use function getcwd;
use function sys_get_temp_dir;
use function uniqid;

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

    public function test_resolve_ignores_the_git_repository_of_the_current_directory(): void
    {
        $project = sys_get_temp_dir() . '/phel-version-' . uniqid();
        exec('git init -q ' . escapeshellarg($project)
            . ' && git -C ' . escapeshellarg($project) . ' -c user.name=t -c user.email=t@t commit -q --allow-empty -m init'
            . ' && git -C ' . escapeshellarg($project) . ' rev-parse --short=7 HEAD', $output, $status);
        self::assertSame(0, $status);
        $projectHead = $output[0];

        $cwd = (string) getcwd();
        chdir($project);
        try {
            $version = new VersionResolver()->resolve();
        } finally {
            chdir($cwd);
            exec('rm -rf ' . escapeshellarg($project));
        }

        self::assertStringNotContainsString($projectHead, $version);
    }

    public function test_a_phel_root_outside_git_falls_back_to_composer_metadata(): void
    {
        $version = new VersionResolver(sys_get_temp_dir())->resolve();

        self::assertStringStartsWith(VersionFinder::LATEST_VERSION, $version);
    }
}
