<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\String_\UseClassKeywordForClassNameResolutionRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
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

        UseClassKeywordForClassNameResolutionRector::class => [
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/LiteralEmitter.php',
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/NodeEmitter/DefEmitter',
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/NodeEmitter/GlobalVarEmitter.php',
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/NodeEmitter/IfEmitter.php',
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/NodeEmitter/MapEmitter.php',
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/NodeEmitter/MethodEmitter.php',
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/NodeEmitter/NsEmitter.php',
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/NodeEmitter/SetVarEmitter.php',
            __DIR__ . '/src/php/Transpiler/Domain/Emitter/OutputEmitter/NodeEmitter/VectorEmitter.php',
            __DIR__ . '/tests/php/Unit/Transpiler/Emitter/OutputEmitter/NodeEmitter/ApplyEmitterTest.php',
            __DIR__ . '/tests/php/Unit/Transpiler/Emitter/OutputEmitter/NodeEmitter/FnAsClassEmitterTest.php',
        ],

        PrivatizeFinalClassPropertyRector::class => [
            __DIR__ . '/tests/php/Unit/Printer/TypePrinter/StubStruct.php',
        ],

        PreferPHPUnitThisCallRector::class,
        UseClassKeywordForClassNameResolutionRector::class,
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::STRICT_BOOLEANS,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        LevelSetList::UP_TO_PHP_82,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_90,
    ]);

    $rectorConfig->importNames();
    $rectorConfig->removeUnusedImports();
};
