<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Runner;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Application\BundledNamespaces;
use Phel\Run\Domain\Runner\NamespaceCollector;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use PHPUnit\Framework\TestCase;

final class NamespaceCollectorTest extends TestCase
{
    public function test_seeds_canonical_dot_form_for_phel_test(): void
    {
        $captured = [];
        $collector = $this->createCollector(
            ['phel.test', 'phel.async', 'phel.json'],
            new NamespaceInformation('/src/app.phel', 'app.main', []),
            $captured,
        );

        $collector->getDependenciesFromPaths(['/src/app.phel']);

        self::assertContains('phel.test', $captured);
        self::assertNotContains('phel\\test', $captured);
        self::assertContains('phel.test', $captured);
    }

    public function test_seeds_every_bundled_namespace(): void
    {
        $captured = [];
        $collector = $this->createCollector(
            ['phel.async', 'phel.html', 'phel.json', 'phel.test', 'app.helper'],
            new NamespaceInformation('/src/app.phel', 'app.main', []),
            $captured,
        );

        $collector->getDependenciesFromPaths(['/src/app.phel']);

        self::assertContains('app.main', $captured);
        self::assertContains('phel.async', $captured);
        self::assertContains('phel.html', $captured);
        self::assertContains('phel.json', $captured);
        self::assertContains('phel.test', $captured);
        self::assertNotContains('app.helper', $captured, 'Non-bundled namespaces must not be seeded.');
    }

    public function test_deduplicates_seeds(): void
    {
        $captured = [];
        $collector = $this->createCollector(
            ['phel.test', 'phel.async'],
            new NamespaceInformation('/src/app.phel', 'phel.async', []),
            $captured,
        );

        $collector->getDependenciesFromPaths(['/src/app.phel']);

        $unique = array_values(array_unique($captured));
        sort($unique);

        self::assertSame(['phel.async', 'phel.test'], $unique);
    }

    /**
     * @param list<string>      $bundledNamespaces Namespace strings the BuildFacade should report on its directory scan
     * @param list<string>|null $captured          Output buffer that captures the seeds passed to getDependenciesForNamespace
     */
    private function createCollector(
        array $bundledNamespaces,
        NamespaceInformation $extractedFromFile,
        array &$captured,
    ): NamespaceCollector {
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $commandFacade = $this->createStub(CommandFacadeInterface::class);

        $commandFacade
            ->method('getSourceDirectories')
            ->willReturn(['/src']);
        $commandFacade
            ->method('getProjectSourceDirectories')
            ->willReturn(['/src']);
        $commandFacade
            ->method('getVendorSourceDirectories')
            ->willReturn(['/vendor']);
        $commandFacade
            ->method('getTestDirectories')
            ->willReturn(['/tests']);

        $buildFacade
            ->method('getNamespaceFromFile')
            ->willReturn($extractedFromFile);

        $buildFacade
            ->method('getNamespaceFromDirectories')
            ->willReturn(array_map(
                static fn(string $ns): NamespaceInformation => new NamespaceInformation('/vendor/' . $ns . '.phel', $ns, []),
                $bundledNamespaces,
            ));

        $buildFacade
            ->expects(self::once())
            ->method('getDependenciesForNamespace')
            ->with(self::anything(), self::callback(
                static function (array $namespaces) use (&$captured): bool {
                    $captured = $namespaces;
                    return true;
                },
            ))
            ->willReturn([]);

        return new NamespaceCollector(
            $buildFacade,
            $commandFacade,
            new BundledNamespaces($buildFacade, $commandFacade),
        );
    }
}
