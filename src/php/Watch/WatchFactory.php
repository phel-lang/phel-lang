<?php

declare(strict_types=1);

namespace Phel\Watch;

use Gacela\Framework\AbstractFactory;
use Phel\Api\ApiFacade;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Watch\Application\ApiProjectReindexer;
use Phel\Watch\Application\MtimeFileSystemScanner;
use Phel\Watch\Application\NamespaceResolver;
use Phel\Watch\Application\NullReloadEventPublisher;
use Phel\Watch\Application\ReloadOrchestrator;
use Phel\Watch\Application\SystemClock;
use Phel\Watch\Application\Watcher\FileWatcherBuilder;
use Phel\Watch\Application\WatchRunner;
use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Domain\FileWatcherInterface;
use Phel\Watch\Domain\NamespaceResolverInterface;
use Phel\Watch\Domain\ProjectReindexerInterface;
use Phel\Watch\Domain\ReloadEventPublisherInterface;
use Phel\Watch\Domain\ReloadOrchestratorInterface;

/**
 * @extends AbstractFactory<WatchConfig>
 */
final class WatchFactory extends AbstractFactory
{
    public function createWatchRunner(?ReloadEventPublisherInterface $publisher = null, ?int $pollIntervalMs = null, ?int $debounceMs = null): WatchRunner
    {
        return new WatchRunner(
            $this->createFileWatcherBuilder($pollIntervalMs, $debounceMs),
            $this->createReloadOrchestrator($publisher),
            $this->getCommandFacade(),
        );
    }

    public function createFileWatcher(?string $preferred = null, ?int $pollIntervalMs = null, ?int $debounceMs = null): FileWatcherInterface
    {
        return $this->createFileWatcherBuilder($pollIntervalMs, $debounceMs)->create($preferred);
    }

    public function createFileWatcherBuilder(?int $pollIntervalMs = null, ?int $debounceMs = null): FileWatcherBuilder
    {
        return new FileWatcherBuilder(
            $this->createFileSystemScanner(),
            $this->createClock(),
            $pollIntervalMs ?? WatchConfig::defaultPollIntervalMs(),
            $debounceMs ?? WatchConfig::defaultDebounceMs(),
        );
    }

    public function createReloadOrchestrator(?ReloadEventPublisherInterface $publisher = null): ReloadOrchestratorInterface
    {
        return new ReloadOrchestrator(
            $this->createNamespaceResolver(),
            $this->getRunFacade(),
            $this->getBuildFacade(),
            $this->createProjectReindexer(),
            $publisher ?? $this->createDefaultPublisher(),
        );
    }

    public function createProjectReindexer(): ProjectReindexerInterface
    {
        return new ApiProjectReindexer($this->getApiFacade());
    }

    public function createNamespaceResolver(): NamespaceResolverInterface
    {
        return new NamespaceResolver();
    }

    public function createFileSystemScanner(): FileSystemScannerInterface
    {
        return new MtimeFileSystemScanner();
    }

    public function createClock(): ClockInterface
    {
        return new SystemClock();
    }

    /**
     * The no-op publisher used in standalone watch mode. Override via the
     * `$publisher` arguments above (or DI) to plug in an nREPL-aware publisher.
     */
    public function createDefaultPublisher(): ReloadEventPublisherInterface
    {
        return new NullReloadEventPublisher();
    }

    public function getRunFacade(): RunFacadeInterface
    {
        return $this->getProvidedDependency(WatchProvider::FACADE_RUN);
    }

    public function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(WatchProvider::FACADE_BUILD);
    }

    public function getApiFacade(): ApiFacade
    {
        return $this->getProvidedDependency(WatchProvider::FACADE_API);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(WatchProvider::FACADE_COMMAND);
    }
}
