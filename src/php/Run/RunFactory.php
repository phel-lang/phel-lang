<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Run\Application\BundledNamespaceDetector;
use Phel\Run\Application\BundledNamespaces;
use Phel\Run\Application\CompileExecutor;
use Phel\Run\Application\EntryPointDetector;
use Phel\Run\Application\EvalExecutor;
use Phel\Run\Application\FileRunner;
use Phel\Run\Application\NamespaceFileTracker;
use Phel\Run\Application\NamespaceLoader;
use Phel\Run\Application\NamespaceRunner;
use Phel\Run\Application\NamespacesLoader;
use Phel\Run\Application\ProjectNamespaceLister;
use Phel\Run\Application\ReplHistoryPathResolver;
use Phel\Run\Application\StructuredEvaluator;
use Phel\Run\Application\Test\Coverage\CoverageAggregator;
use Phel\Run\Application\Test\Coverage\CoverageDriver;
use Phel\Run\Application\Test\CpuCountDetector;
use Phel\Run\Application\Test\ParallelTestOrchestrator;
use Phel\Run\Application\Test\TestWatchLoop;
use Phel\Run\Application\Test\TestWatchRunner;
use Phel\Run\Application\Test\WatchFileScanner;
use Phel\Run\Domain\Repl\ReplCommandFallbackIo;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Repl\ReplCommandSystemIo;
use Phel\Run\Domain\Repl\ReplErrorFormatter;
use Phel\Run\Domain\Repl\ReplHistory;
use Phel\Run\Domain\Repl\ReplPrompt;
use Phel\Run\Domain\Runner\NamespaceCollector;
use Phel\Run\Domain\Runner\NamespaceRunnerInterface;
use Phel\Run\Domain\StdinReaderInterface;
use Phel\Run\Infrastructure\PhpStdinReader;
use Phel\Shared\ColorStyle;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Performance\OpcacheWorkerFlags;
use Phel\Shared\Printer\Printer;
use Phel\Shared\Printer\PrinterInterface;
use Phel\Shared\ScalarCoercion;
use Phel\Shared\VersionResolver;

use function extension_loaded;
use function is_array;
use function is_dir;
use function mkdir;

/**
 * @extends AbstractFactory<RunConfig>
 */
class RunFactory extends AbstractFactory
{
    public function createNamespaceRunner(): NamespaceRunnerInterface
    {
        return new NamespaceRunner(
            $this->getCommandFacade(),
            $this->getBuildFacade(),
        );
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        /** @var CommandFacadeInterface $facade */
        $facade = $this->getProvidedDependency(RunProvider::FACADE_COMMAND);
        return $facade;
    }

    public function createCoverageDriver(): ?CoverageDriver
    {
        return CoverageDriver::detect();
    }

    public function createCoverageAggregator(string $driverName): CoverageAggregator
    {
        return new CoverageAggregator(
            $this->getCommandFacade(),
            $this->getCommandFacade()->getProjectSourceDirectories(),
            $driverName,
        );
    }

    public function getBuildFacade(): BuildFacadeInterface
    {
        /** @var BuildFacadeInterface $facade */
        $facade = $this->getProvidedDependency(RunProvider::FACADE_BUILD);
        return $facade;
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        /** @var CompilerFacadeInterface $facade */
        $facade = $this->getProvidedDependency(RunProvider::FACADE_COMPILER);
        return $facade;
    }

    public function createNamespaceCollector(): NamespaceCollector
    {
        return new NamespaceCollector(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
        );
    }

    public function createProjectNamespaceLister(): ProjectNamespaceLister
    {
        return new ProjectNamespaceLister(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
        );
    }

    public function createBundledNamespaces(): BundledNamespaces
    {
        return new BundledNamespaces(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
        );
    }

    public function createBundledNamespaceDetector(): BundledNamespaceDetector
    {
        return new BundledNamespaceDetector(
            $this->createBundledNamespaces(),
        );
    }

    public function createColorStyle(): ColorStyleInterface
    {
        return ColorStyle::withStyles();
    }

    public function createPrinter(): PrinterInterface
    {
        return Printer::readableWithColor();
    }

    /**
     * Builds the REPL input handler. Prefers the readline-backed
     * {@see ReplCommandSystemIo} (line editing plus persisted history) when the
     * `readline` extension is loaded, falling back to {@see ReplCommandFallbackIo}
     * for environments without it (e.g. CI, web SAPI).
     */
    public function createReplCommandIo(): ReplCommandIoInterface
    {
        if (extension_loaded('readline')) {
            return new ReplCommandSystemIo(
                $this->createReplHistoryPathResolver()->resolve(),
                $this->getCommandFacade(),
                $this->getApiFacade(),
                $this->createReplErrorFormatter(),
            );
        }

        return new ReplCommandFallbackIo(
            $this->getCommandFacade(),
            $this->createReplErrorFormatter(),
        );
    }

    public function createReplHistoryPathResolver(): ReplHistoryPathResolver
    {
        return new ReplHistoryPathResolver($this->getConfig()->getAppRootDir());
    }

    public function createReplErrorFormatter(): ReplErrorFormatter
    {
        return new ReplErrorFormatter(
            $this->getCommandFacade()->getExceptionHintResolver(),
            $this->getCommandFacade()->getExceptionPrinter(),
            $this->createColorStyle(),
        );
    }

    public function createReplHistory(): ReplHistory
    {
        return new ReplHistory($this->getCompilerFacade()->getGlobalEnvironment());
    }

    public function createReplPrompt(): ReplPrompt
    {
        return new ReplPrompt($this->getCompilerFacade());
    }

    public function createNamespacesLoader(): NamespacesLoader
    {
        return new NamespacesLoader(
            $this->getCommandFacade(),
            $this->getBuildFacade(),
        );
    }

    public function getApiFacade(): ApiFacadeInterface
    {
        /** @var ApiFacadeInterface $facade */
        $facade = $this->getProvidedDependency(RunProvider::FACADE_API);
        return $facade;
    }

    public function getFilesystemFacade(): FilesystemFacadeInterface
    {
        /** @var FilesystemFacadeInterface $facade */
        $facade = $this->getProvidedDependency(RunProvider::FACADE_FILESYSTEM);
        return $facade;
    }

    /**
     * @return list<ModuleHealthCheckInterface>
     */
    public function getModuleHealthChecks(): array
    {
        return [
            $this->getBuildFacade()->getHealthCheck(),
            $this->getFilesystemFacade()->getHealthCheck(),
        ];
    }

    public function createVersionResolver(): VersionResolver
    {
        return new VersionResolver();
    }

    public function createEvalExecutor(): EvalExecutor
    {
        return new EvalExecutor(
            $this->createReplCommandIo(),
            $this->createColorStyle(),
            $this->createPrinter(),
            $this->getCompilerFacade(),
            $this->getConfig()->getOptimizationLevel(),
        );
    }

    public function createCompileExecutor(): CompileExecutor
    {
        return new CompileExecutor(
            $this->getCompilerFacade(),
            $this->getConfig()->getOptimizationLevel(),
        );
    }

    public function createNamespaceLoader(): NamespaceLoader
    {
        return new NamespaceLoader(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
            $this->getCompilerFacade(),
            $this->createBundledNamespaces(),
            new NamespaceFileTracker(),
            $this->getConfig()->getReplStartupFile(),
        );
    }

    public function createFileRunner(): FileRunner
    {
        return new FileRunner(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
            $this->createBundledNamespaceDetector(),
        );
    }

    public function createStructuredEvaluator(): StructuredEvaluator
    {
        return new StructuredEvaluator(
            $this->getCompilerFacade(),
        );
    }

    public function createEntryPointDetector(): EntryPointDetector
    {
        return new EntryPointDetector(
            $this->getCommandFacade(),
        );
    }

    public function createStdinReader(): StdinReaderInterface
    {
        return new PhpStdinReader();
    }

    public function createParallelTestOrchestrator(): ParallelTestOrchestrator
    {
        return new ParallelTestOrchestrator(
            PHP_BINARY,
            $this->resolvePhelBinaryPath(),
            $this->resolveOpcacheWorkerFlags(),
        );
    }

    public function createCpuCountDetector(): CpuCountDetector
    {
        return new CpuCountDetector();
    }

    public function createTestWatchRunner(): TestWatchRunner
    {
        return new TestWatchRunner(
            $this->getCommandFacade(),
            new TestWatchLoop(new WatchFileScanner()),
        );
    }

    /**
     * `-d` flags that point the whole worker pool at one on-disk OPcache file
     * cache, so worker N reuses the compiled `.php` worker 1 already parsed.
     * No-op when OPcache is unavailable or the cache dir cannot be prepared.
     *
     * @return list<string>
     */
    private function resolveOpcacheWorkerFlags(): array
    {
        if (!extension_loaded('Zend OPcache')) {
            return [];
        }

        // opcache.file_cache must be an absolute, existing directory or PHP
        // aborts at startup, so create it before any worker is spawned. It
        // lives under the temp dir (not the compiled-code cache) so it survives
        // `phel clear-cache` and keeps paying off across runs.
        $cacheDir = $this->getFilesystemFacade()->getTempDir() . '/opcache-workers';
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
            return [];
        }

        return OpcacheWorkerFlags::forFileCache(true, $cacheDir);
    }

    private function resolvePhelBinaryPath(): string
    {
        $script = ScalarCoercion::toString($_SERVER['SCRIPT_FILENAME'] ?? null);
        if ($script !== '') {
            return $script;
        }

        $argv = $_SERVER['argv'] ?? null;
        $firstArg = is_array($argv) ? ScalarCoercion::toString($argv[0] ?? null) : '';
        if ($firstArg !== '') {
            return $firstArg;
        }

        return __DIR__ . '/../../../bin/phel';
    }
}
