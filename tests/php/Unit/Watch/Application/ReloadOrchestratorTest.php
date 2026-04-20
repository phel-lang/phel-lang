<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application;

use Gacela\Framework\Health\ModuleHealthCheckInterface;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Run\Domain\Repl\EvalResult;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Watch\Application\NamespaceResolver;
use Phel\Watch\Application\ReloadOrchestrator;
use Phel\Watch\Domain\ProjectReindexerInterface;
use Phel\Watch\Domain\ReloadEventPublisherInterface;
use Phel\Watch\Transfer\WatchEvent;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ReloadOrchestratorTest extends TestCase
{
    public function test_it_reloads_changed_namespace_and_dependencies_in_order(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'watch-ro-');
        self::assertNotFalse($file);

        try {
            file_put_contents($file, "(ns app\\core)\n");

            $infoA = new NamespaceInformation($file, 'app\\core', []);
            $infoB = new NamespaceInformation($file . '-b', 'app\\consumer', ['app\\core']);

            $build = new FakeBuildFacade([$infoA, $infoB]);
            $run = new FakeRunFacade();
            $reindexer = new FakeProjectReindexer();
            $publisher = new RecordingPublisher();

            $orchestrator = new ReloadOrchestrator(
                new NamespaceResolver(),
                $run,
                $build,
                $reindexer,
                $publisher,
            );

            $reloaded = $orchestrator->handleChanges(
                [new WatchEvent($file, WatchEvent::KIND_MODIFIED)],
                ['/src'],
            );

            self::assertSame(['app\\core', 'app\\consumer'], $reloaded);
            self::assertSame(['app\\core', 'app\\consumer'], $publisher->lastNamespaces);
            self::assertSame(2, $run->evalFileCount);
            self::assertSame(1, $reindexer->count);
        } finally {
            @unlink($file);
        }
    }

    public function test_it_skips_deleted_files(): void
    {
        $build = new FakeBuildFacade([]);
        $run = new FakeRunFacade();
        $reindexer = new FakeProjectReindexer();
        $publisher = new RecordingPublisher();

        $orchestrator = new ReloadOrchestrator(
            new NamespaceResolver(),
            $run,
            $build,
            $reindexer,
            $publisher,
        );

        $reloaded = $orchestrator->handleChanges(
            [new WatchEvent('/gone.phel', WatchEvent::KIND_DELETED)],
            ['/src'],
        );

        self::assertSame([], $reloaded);
        self::assertSame([], $publisher->lastNamespaces);
        self::assertSame(0, $run->evalFileCount);
        self::assertSame(0, $reindexer->count);
    }

    public function test_it_tolerates_eval_failures_without_aborting_chain(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'watch-ro-');
        self::assertNotFalse($file);

        try {
            file_put_contents($file, "(ns app\\broken)\n");

            $infoA = new NamespaceInformation($file, 'app\\broken', []);
            $infoB = new NamespaceInformation($file . '-b', 'app\\healthy', ['app\\broken']);

            $build = new FakeBuildFacade([$infoA, $infoB]);
            $run = new FakeRunFacade(throwOnCall: 1);
            $reindexer = new FakeProjectReindexer();
            $publisher = new RecordingPublisher();

            $orchestrator = new ReloadOrchestrator(
                new NamespaceResolver(),
                $run,
                $build,
                $reindexer,
                $publisher,
            );

            $reloaded = $orchestrator->handleChanges(
                [new WatchEvent($file, WatchEvent::KIND_MODIFIED)],
                ['/src'],
            );

            self::assertSame(['app\\healthy'], $reloaded);
        } finally {
            @unlink($file);
        }
    }
}

final readonly class FakeBuildFacade implements BuildFacadeInterface
{
    /**
     * @param list<NamespaceInformation> $dependencies
     */
    public function __construct(
        private array $dependencies,
    ) {}

    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        return $this->dependencies;
    }

    public function getNamespaceFromFile(string $filename): NamespaceInformation
    {
        throw new RuntimeException('not implemented');
    }

    public function getNamespaceFromDirectories(array $directories): array
    {
        return [];
    }

    public function compileFile(string $src, string $dest): CompiledFile
    {
        throw new RuntimeException('not implemented');
    }

    public function evalFile(string $src): CompiledFile
    {
        throw new RuntimeException('not implemented');
    }

    public function compileProject(BuildOptions $options): array
    {
        return [];
    }

    public function clearCache(): array
    {
        return [];
    }

    public function getHealthCheck(): ModuleHealthCheckInterface
    {
        throw new RuntimeException('not implemented');
    }

    public function getOutputDirectory(): string
    {
        return '';
    }
}

final class FakeRunFacade implements RunFacadeInterface
{
    public int $evalFileCount = 0;

    public function __construct(
        private readonly int $throwOnCall = 0,
    ) {}

    public function evalFile(NamespaceInformation $info): void
    {
        ++$this->evalFileCount;
        if ($this->evalFileCount === $this->throwOnCall) {
            throw new RuntimeException('boom');
        }
    }

    public function structuredEval(string $phelCode, CompileOptions $compileOptions): EvalResult
    {
        return EvalResult::success(null);
    }

    public function runNamespace(string $namespace): void {}

    public function runFile(string $filename): void {}

    public function eval(string $phelCode, CompileOptions $compileOptions): mixed
    {
        return null;
    }

    public function getAllPhelDirectories(): array
    {
        return [];
    }

    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        return [];
    }

    public function getDependenciesFromPaths(array $paths): array
    {
        return [];
    }

    public function getNamespaceFromFile(string $fileOrPath): NamespaceInformation
    {
        throw new RuntimeException('not implemented');
    }

    public function writeLocatedException(OutputInterface $output, CompilerException $e): void {}

    public function writeStackTrace(OutputInterface $output, Throwable $e): void {}

    public function getLoadedNamespaces(): array
    {
        return [];
    }

    public function getVersion(): string
    {
        return 'test';
    }

    public function loadPhelNamespaces(?string $replStartupFile = null): void {}
}

final class FakeProjectReindexer implements ProjectReindexerInterface
{
    public int $count = 0;

    public function reindex(array $srcDirs): void
    {
        ++$this->count;
    }
}

final class RecordingPublisher implements ReloadEventPublisherInterface
{
    /** @var list<string> */
    public array $lastNamespaces = [];

    /** @var list<WatchEvent> */
    public array $lastEvents = [];

    public function publish(array $events, array $reloadedNamespaces): void
    {
        $this->lastEvents = $events;
        $this->lastNamespaces = $reloadedNamespaces;
    }
}
