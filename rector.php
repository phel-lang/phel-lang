<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src/php',
        __DIR__ . '/tests/php',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/tests/php/*/out/*',
        __DIR__ . '/tests/php/*/gacela-class-names.php',
        __DIR__ . '/tests/php/*/gacela-custom-services.php',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
    ]);
};
