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
        $seeds = $this->captureSeedsForBundledScan(['phel.test', 'phel.async', 'phel.json']);

        self::assertContains('phel.test', $seeds);
        self::assertNotContains('phel\\test', $seeds);
    }

    public function test_seeds_every_bundled_namespace(): void
    {
        $seeds = $this->captureSeedsForBundledScan(
            ['phel.async', 'phel.html', 'phel.json', 'phel.test', 'app.helper'],
        );

        self::assertContains('app.main', $seeds);
        self::assertContains('phel.async', $seeds);
        self::assertContains('phel.html', $seeds);
        self::assertContains('phel.json', $seeds);
        self::assertContains('phel.test', $seeds);
        self::assertNotContains('app.helper', $seeds, 'Non-bundled namespaces must not be seeded.');
    }

    public function test_deduplicates_seeds(): void
    {
        $seeds = $this->captureSeedsForBundledScan(
            ['phel.test', 'phel.async'],
            extractedFromFileNamespace: 'phel.async',
        );

        $unique = array_values(array_unique($seeds));
        sort($unique);

        self::assertSame(['phel.async', 'phel.test'], $unique);
    }

    /**
     * Run the collector against a stubbed bundled-namespace scan and return
     * the seed list it produces.
     *
     * @param list<string> $bundledScanResult Namespace strings the BuildFacade should report on its directory scan
     *
     * @return list<string>
     */
    private function captureSeedsForBundledScan(
        array $bundledScanResult,
        string $extractedFromFileNamespace = 'app.main',
    ): array {
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $commandFacade = $this->createStub(CommandFacadeInterface::class);

        $commandFacade->method('getSourceDirectories')->willReturn(['/src']);
        $commandFacade->method('getProjectSourceDirectories')->willReturn(['/src']);
        $commandFacade->method('getVendorSourceDirectories')->willReturn(['/vendor']);
        $commandFacade->method('getTestDirectories')->willReturn(['/tests']);

        $buildFacade
            ->method('getNamespaceFromFile')
            ->willReturn(new NamespaceInformation('/src/app.phel', $extractedFromFileNamespace, []));

        $buildFacade
            ->method('getNamespaceFromDirectories')
            ->willReturn(array_map(
                static fn(string $ns): NamespaceInformation => new NamespaceInformation('/vendor/' . $ns . '.phel', $ns, []),
                $bundledScanResult,
            ));

        $captured = [];
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

        new NamespaceCollector(
            $buildFacade,
            $commandFacade,
            new BundledNamespaces($buildFacade, $commandFacade),
        )->getDependenciesFromPaths(['/src/app.phel']);

        return $captured;
    }
}
