<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Application\BundledNamespaces;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use PHPUnit\Framework\TestCase;

final class BundledNamespacesTest extends TestCase
{
    public function test_returns_only_phel_dot_prefixed_namespaces(): void
    {
        $namespaces = $this->bundledNamespacesFor(
            sourceDirs: ['/src'],
            vendorDirs: ['/vendor'],
            scanResult: ['phel.async', 'phel.json', 'phel.test', 'app.main', 'lib.helper'],
        )->all();

        self::assertSame(['phel.async', 'phel.json', 'phel.test'], $namespaces);
    }

    public function test_deduplicates_duplicates_across_directories(): void
    {
        $namespaces = $this->bundledNamespacesFor(
            sourceDirs: ['/src'],
            vendorDirs: ['/vendor-a', '/vendor-b'],
            scanResult: ['phel.async', 'phel.json', 'phel.async', 'phel.json'],
        )->all();

        self::assertSame(['phel.async', 'phel.json'], $namespaces);
    }

    public function test_returns_namespaces_in_deterministic_order(): void
    {
        $namespaces = $this->bundledNamespacesFor(
            sourceDirs: ['/src'],
            vendorDirs: [],
            scanResult: ['phel.json', 'phel.async', 'phel.html', 'phel.string'],
        )->all();

        self::assertSame(['phel.async', 'phel.html', 'phel.json', 'phel.string'], $namespaces);
    }

    public function test_returns_empty_when_no_directories_to_scan(): void
    {
        $namespaces = $this->bundledNamespacesFor(
            sourceDirs: [],
            vendorDirs: [],
            scanResult: ['phel.async'],
            expectScan: false,
        )->all();

        self::assertSame([], $namespaces);
    }

    public function test_includes_phel_test_subnamespaces(): void
    {
        $namespaces = $this->bundledNamespacesFor(
            sourceDirs: ['/src'],
            vendorDirs: [],
            scanResult: ['phel.test', 'phel.test.gen', 'phel.test.selector'],
        )->all();

        self::assertSame(['phel.test', 'phel.test.gen', 'phel.test.selector'], $namespaces);
    }

    /**
     * @param list<string> $sourceDirs
     * @param list<string> $vendorDirs
     * @param list<string> $scanResult
     */
    private function bundledNamespacesFor(
        array $sourceDirs,
        array $vendorDirs,
        array $scanResult,
        bool $expectScan = true,
    ): BundledNamespaces {
        $commandFacade = $this->createStub(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn($sourceDirs);
        $commandFacade->method('getVendorSourceDirectories')->willReturn($vendorDirs);

        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade
            ->expects($expectScan ? self::once() : self::never())
            ->method('getNamespaceFromDirectories')
            ->willReturn(array_map(
                static fn(string $ns): NamespaceInformation => new NamespaceInformation('/' . $ns . '.phel', $ns, []),
                $scanResult,
            ));

        return new BundledNamespaces($buildFacade, $commandFacade);
    }
}
