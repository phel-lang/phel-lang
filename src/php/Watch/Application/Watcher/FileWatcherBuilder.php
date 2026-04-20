<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Domain\FileWatcherInterface;

use function in_array;
use function strtolower;
use function substr;

/**
 * Picks the best-available watcher backend for the host OS.
 */
final readonly class FileWatcherBuilder
{
    public function __construct(
        private FileSystemScannerInterface $scanner,
        private ClockInterface $clock,
        private int $pollIntervalMs = 500,
        private int $debounceMs = 100,
    ) {}

    public function create(?string $preferred = null): FileWatcherInterface
    {
        $preferred = $preferred === null ? null : strtolower($preferred);

        if ($preferred === PollingWatcher::NAME) {
            return $this->polling();
        }

        if ($preferred === FswatchWatcher::NAME && FswatchWatcher::isAvailable()) {
            return $this->fswatch();
        }

        if ($preferred === InotifyWatcher::NAME && InotifyWatcher::isAvailable()) {
            return $this->inotify();
        }

        // Auto-detect by OS.
        $os = strtolower(substr(PHP_OS_FAMILY, 0, 10));
        if ($os === 'linux' && InotifyWatcher::isAvailable()) {
            return $this->inotify();
        }

        if (in_array($os, ['darwin', 'bsd'], true) && FswatchWatcher::isAvailable()) {
            return $this->fswatch();
        }

        return $this->polling();
    }

    public function polling(): PollingWatcher
    {
        return new PollingWatcher(
            $this->scanner,
            $this->clock,
            $this->pollIntervalMs,
            $this->debounceMs,
        );
    }

    public function inotify(): InotifyWatcher
    {
        return new InotifyWatcher(
            $this->scanner,
            $this->clock,
            'inotifywait',
            $this->debounceMs,
        );
    }

    public function fswatch(): FswatchWatcher
    {
        return new FswatchWatcher(
            $this->scanner,
            $this->clock,
            'fswatch',
            $this->debounceMs,
        );
    }
}
