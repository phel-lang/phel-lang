<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src/php',
        __DIR__ . '/tests/php',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/tests/php/*/out/*',
        __DIR__ . '/tests/php/*/gacela-class-names.php',
        __DIR__ . '/tests/php/*/gacela-custom-services.php',

        SimplifyIfElseToTernaryRector::class => [
            __DIR__ . '/src/php/Lang/Collections/Map/IndexedNode.php',
        ],
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        LevelSetList::UP_TO_PHP_82,
    ]);
};
