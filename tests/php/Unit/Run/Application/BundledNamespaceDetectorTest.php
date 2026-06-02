<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Run\Application\BundledNamespaceDetector;
use Phel\Run\Application\BundledNamespaces;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class BundledNamespaceDetectorTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phel-bundled-detector-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*.phel') ?: [] as $f) {
            @unlink($f);
        }

        if (is_dir($this->tmpDir)) {
            @rmdir($this->tmpDir);
        }
    }

    public function test_returns_empty_when_source_has_no_fqn_reference(): void
    {
        $script = $this->writeScript("(ns demo)\n(println \"hi\")\n");

        $detector = $this->createDetector(['phel.async', 'phel.json']);

        self::assertSame([], $detector->detect($script));
    }

    public function test_returns_only_referenced_bundled_namespaces(): void
    {
        $script = $this->writeScript("(ns demo)\n(phel.async/delay 10)\n");

        $detector = $this->createDetector(['phel.async', 'phel.json', 'phel.html']);

        self::assertSame(['phel.async'], $detector->detect($script));
    }

    public function test_detects_multiple_distinct_references(): void
    {
        $script = $this->writeScript(
            "(ns demo)\n(phel.async/delay 10)\n(phel.json/encode {})\n",
        );

        $detector = $this->createDetector(['phel.async', 'phel.json', 'phel.html']);

        $detected = $detector->detect($script);
        sort($detected);

        self::assertSame(['phel.async', 'phel.json'], $detected);
    }

    public function test_supports_legacy_backslash_separator(): void
    {
        $script = $this->writeScript("(ns demo)\n(phel\\async/delay 10)\n");

        $detector = $this->createDetector(['phel.async']);

        self::assertSame(['phel.async'], $detector->detect($script));
    }

    public function test_ignores_references_to_unknown_bundled_namespaces(): void
    {
        $script = $this->writeScript("(ns demo)\n(phel.nonexistent/foo 1)\n");

        $detector = $this->createDetector(['phel.async']);

        self::assertSame([], $detector->detect($script));
    }

    public function test_returns_empty_when_no_bundled_namespaces_exist(): void
    {
        $script = $this->writeScript("(ns demo)\n(phel.async/delay 10)\n");

        $detector = $this->createDetector([]);

        self::assertSame([], $detector->detect($script));
    }

    public function test_returns_empty_when_file_unreadable(): void
    {
        $detector = $this->createDetector(['phel.async']);

        self::assertSame([], $detector->detect($this->tmpDir . '/does-not-exist.phel'));
    }

    public function test_remaps_clojure_dependencies_to_existing_bundled_phel_namespaces(): void
    {
        $detector = $this->createDetector(['phel.test', 'phel.string']);

        self::assertSame(
            ['phel.string', 'phel.test'],
            $detector->remapClojureDependencies(['clojure.test', 'clojure\\string', 'clojure.missing']),
        );
    }

    public function test_remap_skips_bundled_discovery_when_no_clojure_dependencies_exist(): void
    {
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->expects(self::never())->method('getNamespaceFromDirectories');

        $commandFacade = $this->createStub(CommandFacadeInterface::class);

        $detector = new BundledNamespaceDetector(new BundledNamespaces($buildFacade, $commandFacade));

        self::assertSame([], $detector->remapClojureDependencies(['phel.test']));
    }

    public function test_remap_deduplicates_equivalent_clojure_dependencies(): void
    {
        $detector = $this->createDetector(['phel.test']);

        self::assertSame(
            ['phel.test'],
            $detector->remapClojureDependencies(['clojure.test', 'clojure\\test', 'phel.test']),
        );
    }

    /**
     * @param list<string> $bundledNamespaces
     */
    private function createDetector(array $bundledNamespaces): BundledNamespaceDetector
    {
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getNamespaceFromDirectories')->willReturn(
            array_map(
                static fn(string $ns): NamespaceInformation => new NamespaceInformation('/' . $ns . '.phel', $ns, [], true),
                $bundledNamespaces,
            ),
        );

        $commandFacade = $this->createMock(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn([$this->tmpDir]);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);

        return new BundledNamespaceDetector(new BundledNamespaces($buildFacade, $commandFacade));
    }

    private function writeScript(string $contents): string
    {
        $path = $this->tmpDir . '/demo.phel';
        file_put_contents($path, $contents);

        return $path;
    }
}
