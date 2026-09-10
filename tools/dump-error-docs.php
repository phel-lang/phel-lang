<?php

declare(strict_types=1);

/**
 * Regenerates the error-code pages under `docs/errors/` that
 * `ErrorCodeInventoryTest` gates on.
 *
 * Run it through `composer error-docs:update`. Every page is rendered from
 * `Phel\Shared\Exceptions\ErrorCodeCatalog`, so a code is documented by adding
 * its entry to the catalog and running this, never by editing a page.
 */

use PhelTest\Support\ErrorDocs;

require_once \dirname(__DIR__) . '/vendor/autoload.php';

$docs = ErrorDocs::fromRepositoryRoot(ErrorDocs::repositoryRoot());
$directory = $docs->outputDirectory();

if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
    fwrite(STDERR, \sprintf("Cannot create %s.\n", $directory));
    exit(1);
}

$pages = $docs->render();
$changed = [];

foreach ($pages as $name => $contents) {
    $path = $directory . '/' . $name;
    $before = is_file($path) ? (string) file_get_contents($path) : '';

    if ($before === $contents) {
        continue;
    }

    file_put_contents($path, $contents);
    $changed[] = $name;
}

// A range that loses its last code loses its page. The test compares the whole
// directory, so a page left behind would fail it with nothing to point at.
foreach (glob($directory . '/*.md') ?: [] as $path) {
    $name = basename($path);

    if (isset($pages[$name])) {
        continue;
    }

    fwrite(STDOUT, \sprintf("Removing page for a range that no longer has codes: %s\n", $path));
    unlink($path);
    $changed[] = $name;
}

if ($changed === []) {
    fwrite(STDOUT, \sprintf("Error code pages are unchanged (%d pages).\n", \count($pages)));
    exit(0);
}

sort($changed);

fwrite(STDOUT, \sprintf(
    "Error code pages updated in docs/errors/: %s\nReview the diff, then add the CHANGELOG entry it deserves.\n",
    implode(', ', $changed),
));
