<?php

declare(strict_types=1);

/**
 * Regenerates the standard-library API snapshot that `CoreApiSurfaceTest` gates on.
 *
 * Run it through `composer core-api:update`. The resulting diff is the source
 * compatibility review: see `docs/spec/language-surface.md` for what 1.x promises
 * about Phel source.
 */

use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PhelTest\Support\CoreApiSurface;

require_once \dirname(__DIR__) . '/vendor/autoload.php';

// The same bootstrap the integration test uses, against the same fixture config,
// so the generated file and the gate cannot disagree about which namespaces load.
$bootstrapDir = \dirname(__DIR__) . '/tests/php/Integration/Api';

Phel::bootstrap($bootstrapDir);
Phel::clear();
Symbol::resetGen();
GlobalEnvironmentSingleton::initializeNew();

$path = CoreApiSurface::snapshotPath();
$before = is_file($path) ? (string) file_get_contents($path) : '';
$after = CoreApiSurface::withApiFacade()->render();

file_put_contents($path, $after);

if ($before === $after) {
    fwrite(STDOUT, "Standard-library API snapshot is unchanged.\n");
    exit(0);
}

fwrite(STDOUT, "Standard-library API snapshot updated: tests/php/Integration/Api/core-api.snapshot.txt\n");
