<?php

declare(strict_types=1);

namespace Phel\Debug;

use SplFileObject;
use Throwable;

use function count;
use function is_string;
use function sprintf;

/**
 * Line-by-line execution tracer using tick functions.
 *
 * Logs execution traces with timestamp, file:line, and source code to a file.
 * Supports namespace filtering to focus on specific parts of the codebase.
 *
 * Features:
 * - Millisecond-precision timestamps
 * - Phel file-based filtering (extracts original .phel file from PHP comments)
 * - Automatic vendor directory exclusion
 * - Duplicate location filtering
 * - Buffered writes for performance
 * - File handle caching to prevent "too many open files" errors
 */
final class DebugLineTap
{
    private const int MAX_BUFFER_SIZE = 50;

    private const int FLUSH_INTERVAL_MS = 250;

    private const int MAX_FILE_CACHE = 20;

    private const int MAX_NAMESPACE_CACHE = 100;

    private static ?self $instance = null;

    /** @var array<string> */
    private array $buffer = [];

    private int $bufferSize = 0;

    private float $lastFlushTime;

    private string $lastLocation = '';

    /** @var array<string, SplFileObject> */
    private array $fileCache = [];

    private readonly int $processId;

    /** @var array<string, string|null> Cache of file path to Phel source file mappings */
    private array $phelSourceCache = [];

    private function __construct(
        private readonly string $logPath,
        private readonly ?string $phelFileFilter = null,
    ) {
        $this->lastFlushTime = microtime(true);
        $this->processId = getmypid();

        $this->writeHeader();
        register_tick_function($this->onTick(...));
        register_shutdown_function($this->flush(...));
    }

    /**
     * Enable debug tracing.
     *
     * @param string|null $phelFileFilter Optional Phel file name to filter traces (e.g., "core" or "boot")
     * @param string      $logPath        Path to the debug log file
     */
    public static function enable(
        ?string $phelFileFilter = null,
        string $logPath = './phel-debug.log',
    ): void {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self($logPath, $phelFileFilter);
    }

    /**
     * Disable debug tracing and flush remaining buffer.
     */
    public static function disable(): void
    {
        if (!self::$instance instanceof self) {
            return;
        }

        self::$instance->flush();
        unregister_tick_function(self::$instance->onTick(...));
        self::$instance = null;
    }

    /**
     * Check if debug tracing is currently enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$instance instanceof self;
    }

    /**
     * Tick handler that captures execution traces.
     *
     * Called automatically by PHP's tick mechanism when enabled.
     */
    private function onTick(): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        if ($trace === []) {
            return;
        }

        $frame = $trace[0];
        $file = $frame['file'] ?? null;
        $line = $frame['line'] ?? null;

        if ($file === null || $line === null) {
            return;
        }

        // Skip vendor files unless explicitly debugging framework internals
        if (str_contains($file, '/vendor/')) {
            return;
        }

        $location = sprintf('%s:%d', $file, $line);

        // Skip duplicate consecutive locations
        if ($location === $this->lastLocation) {
            return;
        }

        $this->lastLocation = $location;

        // Apply Phel file filter if set
        if ($this->phelFileFilter !== null && !$this->matchesPhelFileFilter($file)) {
            return;
        }

        $sourceLine = $this->getSourceLine($file, $line);
        $timestamp = date('H:i:s.') . sprintf('%03d', (int)(microtime(true) * 1000) % 1000);

        $entry = sprintf(
            "[%s] %s:%d | %s\n",
            $timestamp,
            $file,
            $line,
            $sourceLine,
        );

        $this->buffer[] = $entry;
        ++$this->bufferSize;

        // Flush if buffer is full or timeout reached
        $now = microtime(true);
        $elapsed = ($now - $this->lastFlushTime) * 1000; // ms

        if ($this->bufferSize >= self::MAX_BUFFER_SIZE || $elapsed >= self::FLUSH_INTERVAL_MS) {
            $this->flush();
        }
    }

    /**
     * Flush buffered entries to the log file.
     */
    private function flush(): void
    {
        if ($this->bufferSize === 0) {
            return;
        }

        $content = implode('', $this->buffer);
        file_put_contents($this->logPath, $content, FILE_APPEND | LOCK_EX);

        $this->buffer = [];
        $this->bufferSize = 0;
        $this->lastFlushTime = microtime(true);
    }

    /**
     * Write the debug log header.
     */
    private function writeHeader(): void
    {
        $header = sprintf(
            "=== Phel Debug Trace - Started at %s (PID: %d) ===\n",
            date('Y-m-d H:i:s'),
            $this->processId,
        );

        if ($this->phelFileFilter !== null) {
            $header .= sprintf("Phel file filter: %s\n", $this->phelFileFilter);
        }

        $header .= "=======================================================\n\n";

        file_put_contents($this->logPath, $header);
    }

    /**
     * Get the source line(s) from a file with caching.
     *
     * Captures complete PHP forms when they span multiple lines.
     */
    private function getSourceLine(string $file, int $line): string
    {
        try {
            if (!isset($this->fileCache[$file])) {
                if (!file_exists($file)) {
                    return '<file not found>';
                }

                // Limit cache size to prevent "too many open files" error
                if (count($this->fileCache) >= self::MAX_FILE_CACHE) {
                    array_shift($this->fileCache);
                }

                $this->fileCache[$file] = new SplFileObject($file);
            }

            $fileObj = $this->fileCache[$file];

            // Get the current line
            $fileObj->seek($line - 1);
            $content = $fileObj->current();

            if (!is_string($content)) {
                return '<invalid line>';
            }

            $trimmedLine = trim($content);

            // Check if this looks like a complete statement (ends with ;, }, or {)
            if ($this->isCompleteStatement($trimmedLine)) {
                return $trimmedLine;
            }

            // If not complete, try to capture the full form
            return $this->captureCompleteForm($fileObj, $line);
        } catch (Throwable) {
            return '<read error>';
        }
    }

    /**
     * Check if a line represents a complete PHP statement.
     */
    private function isCompleteStatement(string $line): bool
    {
        if ($line === '') {
            return false;
        }

        $lastChar = $line[-1];

        // Complete if ends with semicolon, closing brace, or opening brace
        return $lastChar === ';' || $lastChar === '}' || $lastChar === '{';
    }

    /**
     * Capture a complete PHP form that spans multiple lines.
     */
    private function captureCompleteForm(SplFileObject $fileObj, int $startLine): string
    {
        $lines = [];
        $currentLine = $startLine - 1;
        $maxLines = 20; // Limit to prevent reading entire file

        // Try to find the start of the statement by going backwards
        $searchLine = $currentLine;
        $linesScanned = 0;
        while ($searchLine > 0 && $linesScanned < $maxLines) {
            $fileObj->seek($searchLine - 1);
            $prevContent = $fileObj->current();

            if (!is_string($prevContent)) {
                break;
            }

            $trimmedPrev = trim($prevContent);

            // Stop if we find a line ending with ; } or {
            if ($trimmedPrev !== '' && $this->isCompleteStatement($trimmedPrev)) {
                break;
            }

            --$searchLine;
            ++$linesScanned;
        }

        // Now read forward from the start to capture the complete form
        $formStart = $searchLine;
        for ($i = 0; $i < $maxLines; ++$i) {
            $fileObj->seek($formStart + $i);
            $content = $fileObj->current();

            if (!is_string($content)) {
                break;
            }

            $trimmed = trim($content);
            if ($trimmed === '') {
                continue;
            }

            $lines[] = $content;

            // Stop if we found a complete statement
            if ($this->isCompleteStatement($trimmed)) {
                break;
            }
        }

        if ($lines === []) {
            return '<unable to capture form>';
        }

        return implode(' ', $lines);
    }

    /**
     * Check if a file matches the Phel file filter.
     */
    private function matchesPhelFileFilter(string $file): bool
    {
        if ($this->phelFileFilter === null) {
            return true;
        }

        $phelSourceFile = $this->getPhelSourceFile($file);
        if ($phelSourceFile === null) {
            return false;
        }

        // Extract basename without extension from the Phel source file path
        // e.g., "/path/to/core.phel" -> "core"
        $basename = basename($phelSourceFile, '.phel');

        // Match against the filter
        return $basename === $this->phelFileFilter;
    }

    /**
     * Extract the Phel source file path from a compiled PHP file.
     *
     * Uses caching to avoid repeated file reads.
     * Looks for comments like: // /Users/chema/Code/phel-lang/phel-lang/src/phel/core.phel
     */
    private function getPhelSourceFile(string $file): ?string
    {
        if (isset($this->phelSourceCache[$file])) {
            return $this->phelSourceCache[$file];
        }

        try {
            if (!file_exists($file)) {
                return $this->cachePhelSource($file, null);
            }

            $content = file_get_contents($file);
            if ($content === false) {
                return $this->cachePhelSource($file, null);
            }

            // Extract Phel source file path from comment
            // Match: // /path/to/file.phel
            if (preg_match('#^//\s+(.+?\.phel)#m', $content, $matches)) {
                return $this->cachePhelSource($file, $matches[1]);
            }

            // No Phel source comment found
            return $this->cachePhelSource($file, null);
        } catch (Throwable) {
            return $this->cachePhelSource($file, null);
        }
    }

    /**
     * Cache a Phel source file path and enforce cache size limit.
     */
    private function cachePhelSource(string $file, ?string $phelSourcePath): ?string
    {
        // Limit cache size to prevent excessive memory usage
        if (count($this->phelSourceCache) >= self::MAX_NAMESPACE_CACHE) {
            array_shift($this->phelSourceCache);
        }

        $this->phelSourceCache[$file] = $phelSourcePath;
        return $phelSourcePath;
    }
}
