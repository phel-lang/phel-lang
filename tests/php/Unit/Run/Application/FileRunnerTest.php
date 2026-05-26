<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Application\BundledNamespaces;
use Phel\Run\Application\FileRunner;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use PHPUnit\Framework\TestCase;

use RuntimeException;

use function file_put_contents;
use function in_array;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class FileRunnerTest extends TestCase
{
    private string $tmpDir = '';

    private string $primarySrc = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phel-runner-' . uniqid();
        $this->primarySrc = $this->tmpDir . '/src';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->primarySrc, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/**/*.phel') ?: [] as $f) {
            @unlink($f);
        }

        foreach (glob($this->tmpDir . '/*.phel') ?: [] as $f) {
            @unlink($f);
        }

        foreach (['/src', ''] as $sub) {
            if (is_dir($this->tmpDir . $sub)) {
                @rmdir($this->tmpDir . $sub);
            }
        }
    }

    public function test_does_not_scan_script_dirname_when_script_resolves_from_primary_dirs(): void
    {
        $script = $this->primarySrc . '/main.phel';
        file_put_contents($script, "(ns mainscript)\n");

        $scriptInfo = new NamespaceInformation($script, 'mainscript', [], true);
        $coreInfo = new NamespaceInformation('/phel/core.phel', 'phel.core', [], true);
        $observedDirsArgs = [];

        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getNamespaceFromFile')->willReturn($scriptInfo);
        $buildFacade->method('getDependenciesForNamespace')->willReturnCallback(
            static function (array $dirs, array $ns) use (&$observedDirsArgs, $scriptInfo, $coreInfo): array {
                $observedDirsArgs[] = $dirs;
                return [$coreInfo, $scriptInfo];
            },
        );
        $buildFacade->method('evalFile')->willReturn(
            new CompiledFile('', '', '', false),
        );

        $commandFacade = $this->createMock(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn([$this->primarySrc]);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);

        $this->createFileRunner($buildFacade, $commandFacade)->run($script);

        self::assertNotSame([], $observedDirsArgs);
        foreach ($observedDirsArgs as $dirs) {
            self::assertFalse(
                in_array($this->primarySrc, $dirs, true) && in_array($this->tmpDir, $dirs, true),
                'getDependenciesForNamespace must not receive the script dirname when the script resolves from configured src dirs',
            );
        }
    }

    public function test_resolves_ad_hoc_requires_from_script_dirname_only_as_fallback(): void
    {
        $script = $this->tmpDir . '/demo.phel';
        $helper = $this->tmpDir . '/helper.phel';
        file_put_contents($script, "(ns demo (:require helper))\n");
        file_put_contents($helper, "(ns helper)\n");

        $scriptInfo = new NamespaceInformation($script, 'demo', ['helper'], true);
        $coreInfo = new NamespaceInformation('/phel/core.phel', 'phel.core', [], true);
        $helperInfo = new NamespaceInformation($helper, 'helper', [], true);

        $observedDirsArgs = [];
        $namespaceFromFileCalls = [];

        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getNamespaceFromFile')->willReturnCallback(
            static function (string $path) use (&$namespaceFromFileCalls, $script, $helper, $scriptInfo, $helperInfo): NamespaceInformation {
                $namespaceFromFileCalls[] = $path;
                if ($path === $script) {
                    return $scriptInfo;
                }

                if ($path === $helper) {
                    return $helperInfo;
                }

                throw new RuntimeException('unexpected getNamespaceFromFile: ' . $path);
            },
        );
        $buildFacade->method('getDependenciesForNamespace')->willReturnCallback(
            static function (array $dirs, array $ns) use (&$observedDirsArgs, $coreInfo): array {
                $observedDirsArgs[] = $dirs;
                return [$coreInfo];
            },
        );

        $evalled = [];
        $buildFacade->method('evalFile')->willReturnCallback(
            static function (string $file) use (&$evalled): CompiledFile {
                $evalled[] = $file;
                return new CompiledFile($file, '', '', false);
            },
        );

        $commandFacade = $this->createMock(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn([$this->primarySrc]);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);

        $this->createFileRunner($buildFacade, $commandFacade)->run($script);

        foreach ($observedDirsArgs as $dirs) {
            self::assertNotContains(
                $this->tmpDir,
                $dirs,
                'Script dirname must never reach the recursive extractor; duplicate-ns warnings would fire for unrelated siblings',
            );
        }

        self::assertContains($helper, $namespaceFromFileCalls, 'Sibling require must resolve via direct file lookup');
        self::assertSame(
            ['/phel/core.phel', $helper, $script],
            $evalled,
            'Eval order: phel.core then fallback deps then script',
        );
    }

    public function test_seeds_bundled_namespaces_for_ad_hoc_script_runs(): void
    {
        $script = $this->tmpDir . '/demo.phel';
        file_put_contents($script, "(ns demo)\n");

        $scriptInfo = new NamespaceInformation($script, 'demo', [], true);
        $coreInfo = new NamespaceInformation('/phel/core.phel', 'phel.core', [], true);
        $asyncInfo = new NamespaceInformation('/phel/async.phel', 'phel.async', [], true);

        $observedSeeds = [];

        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getNamespaceFromFile')->willReturn($scriptInfo);
        $buildFacade->method('getNamespaceFromDirectories')->willReturn([$asyncInfo]);
        $buildFacade->method('getDependenciesForNamespace')->willReturnCallback(
            static function (array $dirs, array $ns) use (&$observedSeeds, $coreInfo, $asyncInfo): array {
                $observedSeeds[] = $ns;
                return [$coreInfo, $asyncInfo];
            },
        );

        $evalled = [];
        $buildFacade->method('evalFile')->willReturnCallback(
            static function (string $file) use (&$evalled): CompiledFile {
                $evalled[] = $file;
                return new CompiledFile($file, '', '', false);
            },
        );

        $commandFacade = $this->createMock(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn([$this->primarySrc]);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);

        $this->createFileRunner($buildFacade, $commandFacade)->run($script);

        self::assertNotSame([], $observedSeeds);
        self::assertContains('phel.async', $observedSeeds[0]);
        self::assertSame(
            ['/phel/core.phel', '/phel/async.phel', $script],
            $evalled,
            'Eval order: bundled namespaces are evaluated before the ad-hoc script',
        );
    }

    public function test_two_hop_ad_hoc_chain_evals_in_dependency_first_order(): void
    {
        $script = $this->tmpDir . '/demo.phel';
        $helper = $this->tmpDir . '/helper.phel';
        $subHelper = $this->tmpDir . '/sub-helper.phel';
        file_put_contents($script, "(ns demo (:require helper))\n");
        file_put_contents($helper, "(ns helper (:require sub-helper))\n");
        file_put_contents($subHelper, "(ns sub-helper)\n");

        $scriptInfo = new NamespaceInformation($script, 'demo', ['helper'], true);
        $helperInfo = new NamespaceInformation($helper, 'helper', ['sub-helper'], true);
        $subHelperInfo = new NamespaceInformation($subHelper, 'sub-helper', [], true);
        $coreInfo = new NamespaceInformation('/phel/core.phel', 'phel.core', [], true);

        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getNamespaceFromFile')->willReturnCallback(
            static fn(string $path): NamespaceInformation => match ($path) {
                $script => $scriptInfo,
                $helper => $helperInfo,
                $subHelper => $subHelperInfo,
                default => throw new RuntimeException('unexpected getNamespaceFromFile: ' . $path),
            },
        );
        $buildFacade->method('getDependenciesForNamespace')->willReturn([$coreInfo]);

        $evalled = [];
        $buildFacade->method('evalFile')->willReturnCallback(
            static function (string $file) use (&$evalled): CompiledFile {
                $evalled[] = $file;
                return new CompiledFile($file, '', '', false);
            },
        );

        $commandFacade = $this->createMock(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn([$this->primarySrc]);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);

        $this->createFileRunner($buildFacade, $commandFacade)->run($script);

        self::assertSame(
            ['/phel/core.phel', $subHelper, $helper, $script],
            $evalled,
            'DFS post-order: sub-helper must eval before helper, helper before script',
        );
    }

    private function createFileRunner(
        BuildFacadeInterface $buildFacade,
        CommandFacadeInterface $commandFacade,
    ): FileRunner {
        return new FileRunner(
            $buildFacade,
            $commandFacade,
            new BundledNamespaces($buildFacade, $commandFacade),
        );
    }
}
