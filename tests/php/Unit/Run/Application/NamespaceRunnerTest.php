<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Run\Application\NamespaceRunner;
use Phel\Shared\CompiledFile;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use PHPUnit\Framework\TestCase;

final class NamespaceRunnerTest extends TestCase
{
    public function test_it_normalizes_backslash_namespace_to_dot_form_before_resolving_dependencies(): void
    {
        $capturedNamespaces = [];

        $buildFacade = $this->createStub(BuildFacadeInterface::class);
        $buildFacade->method('getDependenciesForNamespace')
            ->willReturnCallback(static function (array $dirs, array $ns) use (&$capturedNamespaces): array {
                $capturedNamespaces = $ns;
                return [];
            });
        $buildFacade->method('evalFile')
            ->willReturn(new CompiledFile('', '', '', false));

        $commandFacade = $this->createStub(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn([]);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);

        $runner = new NamespaceRunner($commandFacade, $buildFacade);
        $runner->run('cli-skeleton\\modules\\adder-module');

        self::assertContains(
            'cli-skeleton.modules.adder-module',
            $capturedNamespaces,
            'Backslash namespace must be normalized to dot form before getDependenciesForNamespace is called',
        );
        self::assertNotContains(
            'cli-skeleton\\modules\\adder-module',
            $capturedNamespaces,
            'Raw backslash namespace must not be passed to getDependenciesForNamespace',
        );
    }

    public function test_it_passes_dot_form_namespace_unchanged(): void
    {
        $capturedNamespaces = [];

        $buildFacade = $this->createStub(BuildFacadeInterface::class);
        $buildFacade->method('getDependenciesForNamespace')
            ->willReturnCallback(static function (array $dirs, array $ns) use (&$capturedNamespaces): array {
                $capturedNamespaces = $ns;
                return [];
            });
        $buildFacade->method('evalFile')
            ->willReturn(new CompiledFile('', '', '', false));

        $commandFacade = $this->createStub(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn([]);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);

        $runner = new NamespaceRunner($commandFacade, $buildFacade);
        $runner->run('cli-skeleton.modules.adder-module');

        self::assertContains(
            'cli-skeleton.modules.adder-module',
            $capturedNamespaces,
            'Dot-form namespace must pass through unchanged',
        );
    }
}
