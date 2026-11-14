<?php

declare(strict_types=1);

namespace Phel\Watch\Application\Watcher;

use Phel\Watch\Application\SystemClock;
use Phel\Watch\Domain\ClockInterface;
use Phel\Watch\Domain\FileSystemScannerInterface;
use Phel\Watch\Transfer\WatchEvent;

use function array_intersect;
use function escapeshellarg;
use function file_exists;
use function implode;
use function in_array;
use function preg_match;

/**
 * macOS-friendly watcher. Pipes events out of the `fswatch` binary and lets
 * {@see AbstractShellWatcher} handle the process + debounce plumbing. Falls
 * back to the polling watcher when `fswatch` is not available.
 *
 * @internal
 */
final class FswatchWatcher extends AbstractShellWatcher
{
    public const string NAME = 'fswatch';

    /** Event flag names fswatch prints after a path with `--event-flags`. */
    private const array EVENT_FLAGS = [
        'NoOp', 'PlatformSpecific', 'Created', 'Updated', 'Removed', 'Renamed',
        'OwnerModified', 'AttributeModified', 'MovedFrom', 'MovedTo',
        'IsFile', 'IsDir', 'IsSymLink', 'Link', 'Overflow', 'CloseWrite',
    ];

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

    /**
     * `-0` ends every record with NUL, so a path may hold spaces or newlines.
     *
     * @return non-empty-string
     */
    protected function recordSeparator(): string
    {
        return "\0";
    }

    /**
     * A record is the path followed by its event flags, each after a space:
     * `/src/a.phel Updated IsFile`. Flags are peeled off the end, so a space
     * inside the path survives.
     */
    protected function parseLine(string $line): ?WatchEvent
    {
        $path = $line;
        $flags = [];
        while (preg_match('/^(.*) ([A-Za-z]+)$/s', $path, $match) === 1 && in_array($match[2], self::EVENT_FLAGS, true)) {
            $path = $match[1];
            $flags[] = $match[2];
        }

        if (preg_match('/\.(phel|cljc)$/', $path) !== 1) {
            return null;
        }

        return new WatchEvent($path, $this->kindOf($path, $flags));
    }

    /**
     * @param list<string> $flags
     */
    private function kindOf(string $path, array $flags): string
    {
        // FSEvents merges the flags of quick changes to one path, so a delete
        // and re-create can arrive as one `Created Removed` record, and a
        // rename reports both names. Whether the file is there now decides.
        $mayBeGone = array_intersect($flags, ['Removed', 'Renamed', 'MovedFrom', 'MovedTo']) !== [];
        if ($mayBeGone && !file_exists($path)) {
            return WatchEvent::KIND_DELETED;
        }

        if (array_intersect($flags, ['Created', 'Renamed', 'MovedFrom', 'MovedTo']) !== []) {
            return WatchEvent::KIND_CREATED;
        }

        return WatchEvent::KIND_MODIFIED;
    }
}
