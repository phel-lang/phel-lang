<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Transfer\WatchEvent;

use function escapeshellarg;
use function implode;
use function trim;

/**
 * macOS-friendly watcher. Pipes events out of the `fswatch` binary and lets
 * {@see AbstractShellWatcher} handle the process + debounce plumbing. Falls
 * back to the polling watcher when `fswatch` is not available.
 */
final class FswatchWatcher extends AbstractShellWatcher
{
    public const string NAME = 'fswatch';

    public function __construct(
        FileSystemScannerInterface $scanner,
        ClockInterface $clock = new SystemClock(),
        string $binaryPath = 'fswatch',
        int $debounceMs = 100,
    ) {
        parent::__construct($scanner, $clock, $binaryPath, $debounceMs);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public static function isAvailable(string $binary = 'fswatch'): bool
    {
        return self::binaryIsOnPath($binary);
    }

    /**
     * @param list<string> $paths
     */
    protected function buildCommand(array $paths): string
    {
        $args = [];
        foreach ($paths as $path) {
            $args[] = escapeshellarg($path);
        }

        return escapeshellarg($this->binaryPath)
            . ' -r -0 -L -0 --event-flags '
            . '--event Created --event Updated --event Removed --event Renamed '
            . '--include=\'\\.phel$\' --include=\'\\.cljc$\' --exclude=\'.*\' '
            . implode(' ', $args);
    }

    protected function parseLine(string $line): ?WatchEvent
    {
        $path = trim($line);
        if ($path === '') {
            return null;
        }

        return new WatchEvent($path, WatchEvent::KIND_MODIFIED);
    }
}
