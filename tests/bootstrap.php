<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

(static function (): void {
    $repoRoot = \dirname(__DIR__);

    // Build artefacts a previously killed integration test may have left
    // behind. While present, NamespaceFileGrouper sees a duplicate primary
    // definition for `phel.core`, `phel.string`, `phel.pprint`, ... in the
    // next run and writes a noisy warning to STDERR.
    $staleDirs = [
        $repoRoot . '/tests/php/Integration/Build/Command/out-load-e2e',
    ];

    foreach ($staleDirs as $dir) {
        if (is_dir($dir)) {
            removeDirectory($dir);
        }
    }
})();

function removeDirectory(string $target): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();
        is_dir($path) ? rmdir($path) : unlink($path);
    }

    rmdir($target);
}
