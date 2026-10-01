<?php

/**
 * Phel Opcache Preload Script
 *
 * Preloads Gacela core + the Gacela pillar files (facade, factory, config,
 * provider) of every module under src/php/ into opcache for a
 * 20-30% throughput boost on long-running PHP-FPM or CLI-server setups.
 *
 * Configure in php.ini (or FPM pool):
 *
 *   opcache.enable=1
 *   opcache.preload=/path/to/phel/build/preload.php
 *   opcache.preload_user=www-data
 *
 * Requires PHP 8.5+ with opcache enabled. Restart PHP-FPM after deploy.
 */

declare(strict_types=1);

if (!\function_exists('opcache_compile_file')) {
    throw new RuntimeException('opcache is not enabled; cannot preload');
}

require_once __DIR__ . '/preload-files.php';

$projectRoot = \dirname(__DIR__);
$gacelaPreload = $projectRoot . '/vendor/gacela-project/gacela/resources/gacela-preload.php';

if (file_exists($gacelaPreload)) {
    require_once $gacelaPreload;
}

$phelFiles = phelPreloadFiles($projectRoot);

$loaded = 0;
$failed = [];

foreach ($phelFiles as $relative) {
    $fullPath = $projectRoot . $relative;
    if (!file_exists($fullPath)) {
        $failed[] = $relative;
        continue;
    }

    try {
        opcache_compile_file($fullPath);
        ++$loaded;
    } catch (Throwable $e) {
        $failed[] = $relative . ' (' . $e->getMessage() . ')';
    }
}

error_log(\sprintf('Phel Opcache Preload: %d files loaded, %d failed', $loaded, \count($failed)));

if ($failed !== []) {
    error_log('Failed: ' . implode(', ', $failed));
}
