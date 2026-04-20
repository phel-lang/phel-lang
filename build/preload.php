<?php

/**
 * Phel Opcache Preload Script
 *
 * Preloads Gacela core + Phel facades/factories into opcache for a
 * 20-30% throughput boost on long-running PHP-FPM or CLI-server setups.
 *
 * Configure in php.ini (or FPM pool):
 *
 *   opcache.enable=1
 *   opcache.preload=/path/to/phel/build/preload.php
 *   opcache.preload_user=www-data
 *
 * Requires PHP 8.4+ with opcache enabled. Restart PHP-FPM after deploy.
 */

declare(strict_types=1);

if (!\function_exists('opcache_compile_file')) {
    throw new RuntimeException('opcache is not enabled; cannot preload');
}

$projectRoot = \dirname(__DIR__);
$gacelaPreload = $projectRoot . '/vendor/gacela-project/gacela/resources/gacela-preload.php';

if (file_exists($gacelaPreload)) {
    require_once $gacelaPreload;
}

$phelFiles = [
    '/src/php/Api/ApiFacade.php',
    '/src/php/Api/ApiFactory.php',
    '/src/php/Api/ApiProvider.php',
    '/src/php/Build/BuildFacade.php',
    '/src/php/Build/BuildFactory.php',
    '/src/php/Build/BuildConfig.php',
    '/src/php/Build/BuildProvider.php',
    '/src/php/Command/CommandFacade.php',
    '/src/php/Command/CommandFactory.php',
    '/src/php/Command/CommandConfig.php',
    '/src/php/Command/CommandProvider.php',
    '/src/php/Compiler/CompilerFacade.php',
    '/src/php/Compiler/CompilerFactory.php',
    '/src/php/Compiler/CompilerConfig.php',
    '/src/php/Compiler/CompilerProvider.php',
    '/src/php/Config/ConfigFacade.php',
    '/src/php/Config/ConfigFactory.php',
    '/src/php/Console/ConsoleFacade.php',
    '/src/php/Console/ConsoleFactory.php',
    '/src/php/Console/ConsoleProvider.php',
    '/src/php/Filesystem/FilesystemFacade.php',
    '/src/php/Filesystem/FilesystemFactory.php',
    '/src/php/Formatter/FormatterFacade.php',
    '/src/php/Formatter/FormatterFactory.php',
    '/src/php/Formatter/FormatterProvider.php',
    '/src/php/Interop/InteropFacade.php',
    '/src/php/Interop/InteropFactory.php',
    '/src/php/Interop/InteropProvider.php',
    '/src/php/Run/RunFacade.php',
    '/src/php/Run/RunFactory.php',
    '/src/php/Run/RunProvider.php',
    '/src/php/Printer/Printer.php',
    '/src/php/Lang/Registry.php',
    '/src/Phel.php',
];

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
