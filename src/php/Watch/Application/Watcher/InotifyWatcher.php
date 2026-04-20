<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Transfer\WatchEvent;

use function count;
use function escapeshellarg;
use function explode;
use function extension_loaded;
use function implode;
use function preg_match;
use function rtrim;
use function str_contains;

/**
 * Linux-friendly watcher. Shell-outs to the `inotifywait` binary (part of
 * `inotify-tools`) and lets {@see AbstractShellWatcher} own the process +
 * debounce plumbing. The pure-`ext-inotify` path is not implemented here;
 * the shell-out is portable, works across distros, and matches the
 * behaviour of the macOS `fswatch` backend.
 */
final class InotifyWatcher extends AbstractShellWatcher
{
    public const string NAME = 'inotify';

    public function __construct(
        FileSystemScannerInterface $scanner,
        ClockInterface $clock = new SystemClock(),
        string $binaryPath = 'inotifywait',
        int $debounceMs = 100,
    ) {
        parent::__construct($scanner, $clock, $binaryPath, $debounceMs);
    }

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * True when either the `inotify` PHP extension is loaded or the
     * `inotifywait` CLI is available on PATH. The latter is the default
     * transport.
     */
    public static function isAvailable(string $binary = 'inotifywait'): bool
    {
        if (extension_loaded('inotify')) {
            return true;
        }

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

        // `-m` monitor, `-r` recursive, events close_write/create/delete/move,
        // format "<file>\t<event>" so parsing stays simple.
        return escapeshellarg($this->binaryPath)
            . ' -m -r --format \'%w%f\t%e\' --quiet '
            . '-e close_write -e create -e delete -e move '
            . implode(' ', $args);
    }

    protected function parseLine(string $line): ?WatchEvent
    {
        $line = rtrim($line);
        if ($line === '') {
            return null;
        }

        $parts = explode("\t", $line, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$path, $events] = $parts;
        if (preg_match('/\.(phel|cljc)$/', $path) !== 1) {
            return null;
        }

        if (str_contains($events, 'DELETE')) {
            $kind = WatchEvent::KIND_DELETED;
        } elseif (str_contains($events, 'CREATE')) {
            $kind = WatchEvent::KIND_CREATED;
        } else {
            $kind = WatchEvent::KIND_MODIFIED;
        }

        return new WatchEvent($path, $kind);
    }
}
