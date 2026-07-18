<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\String_\UseClassKeywordForClassNameResolutionRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessUnionReturnDocblockRector;
use Rector\Php84\Rector\Foreach_\ForeachToArrayAllRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictConstantReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src/php',
        __DIR__ . '/tests/php',
    ])
    ->withSkip([
        __DIR__ . '/**/cache/*',
        __DIR__ . '/**/namespace-cache.php',
        __DIR__ . '/tests/php/*/out/*',
        __DIR__ . '/tests/php/**/out-*/*',
        __DIR__ . '/tests/php/*/PhelGenerated/*',
        __DIR__ . '/tests/php/*/gacela-class-names.php',
        __DIR__ . '/tests/php/*/gacela-custom-services.php',
        // The `foreach` here mutates a referenced `$names` parameter;
        // converting to `array_all` with an arrow fn would silently
        // drop the reference because arrow functions capture by value.
        ForeachToArrayAllRector::class => [
            __DIR__ . '/src/php/Compiler/Domain/Analyzer/Ast/Reference/LocalVarReferences.php',
        ],
        // `ob_get_clean()` returns `string|false`; Rector 2.5 narrows it to
        // `string` inside these catch blocks and strips the cast, which Psalm
        // then rejects as PossiblyFalseArgument.
        RecastingRemovalRector::class => [
            __DIR__ . '/src/php/Run/Application/StructuredEvaluator.php',
        ],
        ReturnTypeFromReturnNewRector::class => [
            __DIR__ . '/tests/php/Unit/Interop/Generator/CompiledPhpMethodBuilderTest.php',
        ],
        ReturnTypeFromStrictConstantReturnRector::class => [
            __DIR__ . '/tests/php/Unit/Interop/Generator/CompiledPhpMethodBuilderTest.php',
        ],
        UseClassKeywordForClassNameResolutionRector::class => [
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/LiteralEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/DefEmitter',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/GlobalVarEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/IfEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/LoadEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/MapEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/MethodEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/NsEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/SetVarEmitter.php',
            __DIR__ . '/src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/VectorEmitter.php',
            __DIR__ . '/tests/php/Unit/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/ApplyEmitterTest.php',
            __DIR__ . '/tests/php/Unit/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/FnAsClassEmitterTest.php',
        ],
        PrivatizeFinalClassPropertyRector::class => [
            __DIR__ . '/tests/php/Unit/Printer/TypePrinter/StubStruct.php',
            __DIR__ . '/tests/php/Unit/Lang/Collections/Struct/FakeStruct.php',
        ],
        PreferPHPUnitThisCallRector::class,
        // Rector 2.5 rule that treats `@return self<T>|null` as redundant with a
        // native `?self` return type, dropping the generics PHPStan needs
        // (missingType.generics) across the Lang collections.
        RemoveUselessUnionReturnDocblockRector::class,
    ])
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::STRICT_BOOLEANS,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        LevelSetList::UP_TO_PHP_84,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_100,
    ])
    ->withImportNames(removeUnusedImports: true);
