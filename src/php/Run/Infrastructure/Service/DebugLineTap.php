<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Service;

use Throwable;

use function count;
use function in_array;
use function sprintf;

/**
 * Line-by-line execution tracer using tick functions.
 *
 * Logs execution traces with timestamp, file:line, and source code to a file.
 */
final class DebugLineTap
{
    private const int MAX_BUFFER_SIZE = 50;

    private const int FLUSH_INTERVAL_MS = 250;

    private const int MAX_LINES_TO_CAPTURE = 20;

    private static ?self $instance = null;

    /** @var array<string> */
    private array $buffer = [];

    private float $lastFlushTime;

    private string $lastLocation = '';

    /** @var array<string, string|null> */
    private array $phelSourceCache = [];

    private function __construct(
        private readonly string $logPath,
        private readonly ?string $phelFileFilter = null,
    ) {
        $this->lastFlushTime = microtime(true);
        $this->writeHeader();
        register_tick_function($this->onTick(...));
        register_shutdown_function($this->flush(...));
    }

    public static function enable(?string $phelFileFilter = null, string $logPath = './phel-debug.log'): void
    {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self($logPath, $phelFileFilter);
    }

    public static function disable(): void
    {
        if (!self::$instance instanceof self) {
            return;
        }

        self::$instance->flush();
        unregister_tick_function(self::$instance->onTick(...));
        self::$instance = null;
    }

    public static function isEnabled(): bool
    {
        return self::$instance instanceof self;
    }

    private function onTick(): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        if ($trace === []) {
            return;
        }

        $file = $trace[0]['file'] ?? null;
        $line = $trace[0]['line'] ?? null;

        if ($file === null || $line === null || str_contains($file, '/vendor/')) {
            return;
        }

        $location = sprintf('%s:%d', $file, $line);
        if ($location === $this->lastLocation) {
            return;
        }

        $this->lastLocation = $location;

        if ($this->phelFileFilter !== null && !$this->matchesFilter($file)) {
            return;
        }

        $this->logEntry($file, $line);
    }

    private function logEntry(string $file, int $line): void
    {
        $source = $this->getSource($file, $line);
        $timestamp = $this->formatTimestamp();

        $this->buffer[] = sprintf("[%s] %s:%d | %s\n", $timestamp, $file, $line, $source);

        if (count($this->buffer) >= self::MAX_BUFFER_SIZE || $this->shouldFlush()) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        file_put_contents($this->logPath, implode('', $this->buffer), FILE_APPEND | LOCK_EX);
        $this->buffer = [];
        $this->lastFlushTime = microtime(true);
    }

    private function shouldFlush(): bool
    {
        $elapsedMs = (microtime(true) - $this->lastFlushTime) * 1000.0;

        return $elapsedMs >= self::FLUSH_INTERVAL_MS;
    }

    private function formatTimestamp(): string
    {
        $milliseconds = ((int)(microtime(true) * 1000.0)) % 1000;

        return date('H:i:s.') . sprintf('%03d', $milliseconds);
    }

    private function writeHeader(): void
    {
        $pid = getmypid();
        $header = sprintf(
            "=== Phel Debug Trace - Started at %s (PID: %s) ===\n",
            date('Y-m-d H:i:s'),
            $pid === false ? 'unknown' : (string)$pid,
        );

        if ($this->phelFileFilter !== null) {
            $header .= sprintf("Phel file filter: %s\n", $this->phelFileFilter);
        }

        $header .= "=======================================================\n\n";
        file_put_contents($this->logPath, $header);
    }

    private function getSource(string $file, int $line): string
    {
        try {
            $lines = file($file);
            if ($lines === false) {
                return '<file not found>';
            }

            return $this->captureCompleteForm($lines, $line);
        } catch (Throwable) {
            return '<read error>';
        }
    }

    /**
     * @param array<string> $lines
     */
    private function captureCompleteForm(array $lines, int $currentLine): string
    {
        $index = $currentLine - 1;
        if (!isset($lines[$index])) {
            return '<invalid line>';
        }

        $currentContent = trim($lines[$index]);
        if ($this->isComplete($currentContent)) {
            return $currentContent;
        }

        // Find statement start
        $start = $index;
        for ($i = $index - 1; $i >= max(0, $index - self::MAX_LINES_TO_CAPTURE); --$i) {
            $trimmed = trim($lines[$i]);
            if ($trimmed !== '' && $this->isComplete($trimmed)) {
                $start = $i + 1;
                break;
            }
        }

        // Capture until complete
        $captured = [];
        for ($i = $start; $i < min(count($lines), $start + self::MAX_LINES_TO_CAPTURE); ++$i) {
            $trimmed = trim($lines[$i]);
            if ($trimmed === '') {
                continue;
            }

            $captured[] = $lines[$i];

            if ($this->isComplete($trimmed)) {
                break;
            }
        }

        return $captured === [] ? '<unable to capture>' : implode(' ', $captured);
    }

    private function isComplete(string $line): bool
    {
        if ($line === '') {
            return false;
        }

        $lastChar = $line[-1];
        return in_array($lastChar, [';', '}', '{'], true);
    }

    private function matchesFilter(string $file): bool
    {
        $phelFile = $this->getPhelSourceFile($file);
        if ($phelFile === null) {
            return false;
        }

        return basename($phelFile, '.phel') === $this->phelFileFilter;
    }

    private function getPhelSourceFile(string $file): ?string
    {
        if (isset($this->phelSourceCache[$file])) {
            return $this->phelSourceCache[$file];
        }

        try {
            $content = file_get_contents($file);
            if ($content === false) {
                return $this->phelSourceCache[$file] = null;
            }

            if (preg_match('#^//\s+(.+?\.phel)#m', $content, $matches)) {
                return $this->phelSourceCache[$file] = $matches[1];
            }

            return $this->phelSourceCache[$file] = null;
        } catch (Throwable) {
            return $this->phelSourceCache[$file] = null;
        }
    }
}
