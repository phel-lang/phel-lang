<?php

declare(strict_types=1);

/**
 * Regenerates the public PHP API snapshot that `PublicApiSurfaceTest` gates on.
 *
 * Run it through `composer api-surface:update`. The resulting diff is the
 * backward-compatibility review: see `docs/stability.md` for which shapes are
 * breaking and which are not.
 */

use PhelTest\Support\PublicApiSurface;

require_once \dirname(__DIR__) . '/vendor/autoload.php';

$path = PublicApiSurface::snapshotPath();
$before = is_file($path) ? (string) file_get_contents($path) : '';
$after = PublicApiSurface::fromRepositoryRoot(PublicApiSurface::repositoryRoot())->render();

file_put_contents($path, $after);

if ($before === $after) {
    fwrite(STDOUT, "Public API snapshot is unchanged.\n");
    exit(0);
}

fwrite(STDOUT, \sprintf(
    "Public API snapshot updated: %s\nReview the diff, then add the CHANGELOG entry it deserves.\n",
    str_replace(PublicApiSurface::repositoryRoot() . '/', '', $path),
));
