<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Generator;
use Phel\Lint\LintFactory;
use Phel\Lsp\LspFactory;
use Phel\Nrepl\NreplFactory;
use Phel\Profile\ProfileFactory;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Watch\WatchFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Satellite modules must depend on the Shared facade *contracts*, not on a
 * neighbour module's concrete facade. The factory getter return type is the
 * one place that pins this down, so we lock it here against regressions.
 */
final class SatelliteFactoryFacadeInjectionTest extends TestCase
{
    #[DataProvider('factoryGetterProvider')]
    public function test_factory_getter_returns_facade_interface(
        string $factory,
        string $method,
        string $expectedInterface,
    ): void {
        $returnType = new ReflectionMethod($factory, $method)->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame($expectedInterface, $returnType->getName());
    }

    public static function factoryGetterProvider(): Generator
    {
        yield 'Lsp run' => [LspFactory::class, 'getRunFacade', RunFacadeInterface::class];

        yield 'Lint run' => [LintFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Lint compiler' => [LintFactory::class, 'getCompilerFacade', CompilerFacadeInterface::class];
        yield 'Lint command' => [LintFactory::class, 'getCommandFacade', CommandFacadeInterface::class];

        yield 'Watch run' => [WatchFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Watch command' => [WatchFactory::class, 'getCommandFacade', CommandFacadeInterface::class];
        yield 'Watch build' => [WatchFactory::class, 'getBuildFacade', BuildFacadeInterface::class];

        yield 'Nrepl run' => [NreplFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Nrepl api' => [NreplFactory::class, 'getApiFacade', ApiFacadeInterface::class];

        yield 'Profile run' => [ProfileFactory::class, 'getRunFacade', RunFacadeInterface::class];
    }

    public function test_run_facade_interface_declares_auto_detect_entry_point(): void
    {
        self::assertTrue(
            method_exists(RunFacadeInterface::class, 'autoDetectEntryPoint'),
            'Profile consumes RunFacade::autoDetectEntryPoint via the Shared contract.',
        );
    }
}
