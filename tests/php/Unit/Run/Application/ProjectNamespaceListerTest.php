<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Run\Application\ProjectNamespaceLister;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

use function array_map;

final class ProjectNamespaceListerTest extends TestCase
{
    public function test_lists_distinct_namespaces_sorted(): void
    {
        $lister = $this->listerFor(
            scanResult: ['app.web', 'app.main', 'phel.core'],
        );

        self::assertSame(['app.main', 'app.web', 'phel.core'], $lister->listAll());
    }

    public function test_deduplicates_namespaces_declared_in_multiple_files(): void
    {
        $lister = $this->listerFor(
            scanResult: ['app.main', 'app.main', 'app.web'],
        );

        self::assertSame(['app.main', 'app.web'], $lister->listAll());
    }

    public function test_scans_source_test_and_vendor_directories_together(): void
    {
        $commandFacade = $this->createStub(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn(['/src']);
        $commandFacade->method('getTestDirectories')->willReturn(['/tests']);
        $commandFacade->method('getVendorSourceDirectories')->willReturn(['/vendor']);

        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade
            ->expects(self::once())
            ->method('getNamespaceFromDirectories')
            ->with(['/src', '/tests', '/vendor'])
            ->willReturn([]);

        new ProjectNamespaceLister($buildFacade, $commandFacade)->listAll();
    }

    /**
     * @param list<string> $scanResult
     */
    private function listerFor(array $scanResult): ProjectNamespaceLister
    {
        $commandFacade = $this->createStub(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn(['/src']);
        $commandFacade->method('getTestDirectories')->willReturn(['/tests']);
        $commandFacade->method('getVendorSourceDirectories')->willReturn(['/vendor']);

        $buildFacade = $this->createStub(BuildFacadeInterface::class);
        $buildFacade
            ->method('getNamespaceFromDirectories')
            ->willReturn(array_map(
                static fn(string $ns): NamespaceInformation => new NamespaceInformation('/' . $ns . '.phel', $ns, []),
                $scanResult,
            ));

        return new ProjectNamespaceLister($buildFacade, $commandFacade);
    }
}
