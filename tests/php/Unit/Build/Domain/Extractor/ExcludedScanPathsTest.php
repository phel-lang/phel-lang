<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Extractor;

use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use PHPUnit\Framework\TestCase;

final class ExcludedScanPathsTest extends TestCase
{
    public function test_none_excludes_nothing(): void
    {
        $paths = ExcludedScanPaths::none();

        self::assertFalse($paths->contains('/repo/src/foo.phel', '/repo/src'));
    }

    public function test_dest_dir_basename_is_pruned_relative_to_scan_root(): void
    {
        $paths = new ExcludedScanPaths(destDirBasename: 'out');

        self::assertTrue($paths->contains('/repo/out/phel/core.phel', '/repo'));
        self::assertFalse($paths->contains('/repo/src/phel/core.phel', '/repo'));
    }

    public function test_dest_dir_basename_does_not_match_sibling_scan_root(): void
    {
        $paths = new ExcludedScanPaths(destDirBasename: 'out');

        self::assertFalse($paths->contains('/other/out/phel/core.phel', '/repo'));
    }

    public function test_absolute_excluded_directory_is_matched_regardless_of_scan_root(): void
    {
        $dir = sys_get_temp_dir() . '/phel-excluded-' . uniqid();
        mkdir($dir, 0777, true);
        $real = realpath($dir);

        try {
            $paths = new ExcludedScanPaths(excludedDirectories: [$dir]);

            self::assertTrue($paths->contains($real . '/nested.phel', '/any/scan/root'));
            self::assertFalse($paths->contains('/elsewhere/file.phel', '/any/scan/root'));
        } finally {
            rmdir($dir);
        }
    }

    public function test_empty_excluded_directory_strings_are_skipped(): void
    {
        $paths = new ExcludedScanPaths(excludedDirectories: ['']);

        self::assertFalse($paths->contains('/anything.phel', '/scan'));
    }

    public function test_unresolvable_excluded_directory_still_prunes_by_literal_prefix(): void
    {
        // Configured output dirs may not exist yet (e.g. before first build);
        // the literal path is still used as a prefix so the subtree is pruned
        // once it materialises.
        $paths = new ExcludedScanPaths(excludedDirectories: ['/not/yet/built']);

        self::assertTrue($paths->contains('/not/yet/built/phel/core.phel', '/scan'));
    }
}
