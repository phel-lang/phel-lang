<?php

declare(strict_types=1);

/**
 * Lists the Phel files worth preloading: the Gacela pillar files of every
 * module under src/php/, plus the registry and the entry point.
 *
 * @return list<string> paths relative to the project root, starting with "/"
 */
function phelPreloadFiles(string $projectRoot): array
{
    $files = [];
    $modulePaths = glob($projectRoot . '/src/php/*', GLOB_ONLYDIR | GLOB_NOSORT) ?: [];
    sort($modulePaths);

    foreach ($modulePaths as $modulePath) {
        $module = basename($modulePath);
        foreach (['Facade', 'Factory', 'Config', 'Provider'] as $pillar) {
            $relative = '/src/php/' . $module . '/' . $module . $pillar . '.php';
            if (is_file($projectRoot . $relative)) {
                $files[] = $relative;
            }
        }
    }

    $files[] = '/src/php/Lang/Registry.php';
    $files[] = '/src/Phel.php';

    return $files;
}
