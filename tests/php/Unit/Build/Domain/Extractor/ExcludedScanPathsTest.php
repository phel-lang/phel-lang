<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Extractor;

use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use PHPUnit\Framework\TestCase;

use function sprintf;

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
        $ds = DIRECTORY_SEPARATOR;

        self::assertTrue($paths->contains(sprintf('%srepo%sout%sphel%score.phel', $ds, $ds, $ds, $ds), $ds . 'repo'));
        self::assertFalse($paths->contains(sprintf('%srepo%ssrc%sphel%score.phel', $ds, $ds, $ds, $ds), $ds . 'repo'));
    }

    public function test_dest_dir_basename_does_not_match_sibling_scan_root(): void
    {
        $paths = new ExcludedScanPaths(destDirBasename: 'out');
        $ds = DIRECTORY_SEPARATOR;

        self::assertFalse($paths->contains(sprintf('%sother%sout%sphel%score.phel', $ds, $ds, $ds, $ds), $ds . 'repo'));
    }

    public function test_absolute_excluded_directory_is_matched_regardless_of_scan_root(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phel-excluded-' . uniqid();
        mkdir($dir, 0777, true);
        $real = realpath($dir);
        $ds = DIRECTORY_SEPARATOR;

        try {
            $paths = new ExcludedScanPaths(excludedDirectories: [$dir]);

            self::assertTrue($paths->contains($real . $ds . 'nested.phel', sprintf('%sany%sscan%sroot', $ds, $ds, $ds)));
            self::assertFalse($paths->contains(sprintf('%selsewhere%sfile.phel', $ds, $ds), sprintf('%sany%sscan%sroot', $ds, $ds, $ds)));
        } finally {
            rmdir($dir);
        }
    }

    public function test_empty_excluded_directory_strings_are_skipped(): void
    {
        $paths = new ExcludedScanPaths(excludedDirectories: ['']);
        $ds = DIRECTORY_SEPARATOR;

        self::assertFalse($paths->contains($ds . 'anything.phel', $ds . 'scan'));
    }

    public function test_unresolvable_excluded_directory_still_prunes_by_literal_prefix(): void
    {
        // Configured output dirs may not exist yet (e.g. before first build);
        // the literal path is still used as a prefix so the subtree is pruned
        // once it materialises.
        $ds = DIRECTORY_SEPARATOR;
        $paths = new ExcludedScanPaths(excludedDirectories: [sprintf('%snot%syet%sbuilt', $ds, $ds, $ds)]);

        self::assertTrue($paths->contains(sprintf('%snot%syet%sbuilt%sphel%score.phel', $ds, $ds, $ds, $ds, $ds), $ds . 'scan'));
    }
}
