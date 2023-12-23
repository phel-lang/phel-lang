<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\CodingStyle\Rector\String_\UseClassKeywordForClassNameResolutionRector;
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

        UseClassKeywordForClassNameResolutionRector::class => [
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/LiteralEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/DefEmitter',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/GlobalVarEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/IfEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/MapEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/MethodEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/NsEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/SetVarEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/VectorEmitter.php',
            __DIR__ . '/tests/php/Unit/Compiler/Emitter/OutputEmitter/NodeEmitter/ApplyEmitterTest.php',
            __DIR__ . '/tests/php/Unit/Compiler/Emitter/OutputEmitter/NodeEmitter/FnAsClassEmitterTest.php',
        ],
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        LevelSetList::UP_TO_PHP_82,
    ]);
};
