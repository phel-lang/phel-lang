<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

(static function (): void {
    // Under paratest each worker is a separate process that re-runs this
    // bootstrap with a distinct TEST_TOKEN. Point the worker at its own system
    // temp dir so every sys_get_temp_dir()-derived path — the compiled-code
    // cache, the Gacela merged-config cache and the parallel-runner opcache
    // dir, both in-process and in spawned `bin/phel` subprocesses (which
    // inherit TMPDIR) — is worker-private and can never race a sibling worker.
    //
    // Read TMPDIR directly rather than sys_get_temp_dir(): the latter caches
    // its result for the lifetime of the process on the first call, so touching
    // it before the putenv() below would lock the cache to the shared base and
    // silently defeat the override.
    $token = getenv('TEST_TOKEN');
    if ($token === false || $token === '') {
        return;
    }

    $base = getenv('TMPDIR');
    if ($base === false || $base === '') {
        $base = DIRECTORY_SEPARATOR . 'tmp';
    }

    $workerTmp = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'phel-test-worker-' . $token;
    if (!is_dir($workerTmp)) {
        @mkdir($workerTmp, 0o777, true);
    }

    putenv('TMPDIR=' . $workerTmp);
    $_ENV['TMPDIR'] = $workerTmp;
    $_SERVER['TMPDIR'] = $workerTmp;

    register_shutdown_function(static function () use ($workerTmp): void {
        removeDirectory($workerTmp);
    });
})();

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
    if (!is_dir($target)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();
        is_dir($path) ? @rmdir($path) : @unlink($path);
    }

    @rmdir($target);
}
