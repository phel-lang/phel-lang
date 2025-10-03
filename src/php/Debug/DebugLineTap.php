<?php

declare(strict_types=1);

namespace Phel\Debug;

use SplFileObject;
use Throwable;

use function is_string;
use function sprintf;

/**
 * Line-by-line execution tracer using tick functions.
 * Logs timestamp, file:line, and source code to a file.
 */
final class DebugLineTap
{
    private const int MAX_BUFFER_SIZE = 50;

    private const int FLUSH_INTERVAL_MS = 250;

    private static ?self $instance = null;

    /** @var array<string> */
    private array $buffer = [];

    private int $bufferSize = 0;

    private float $lastFlushTime;

    private string $lastLocation = '';

    /** @var array<string, SplFileObject> */
    private array $fileCache = [];

    private function __construct(private readonly string $logPath)
    {
        $this->lastFlushTime = microtime(true);
        register_tick_function($this->onTick(...));
        register_shutdown_function([$this, 'flush']);
    }

    public static function enable(string $logPath = './phel-debug.log'): void
    {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self($logPath);
    }

    public static function disable(): void
    {
        if (!self::$instance instanceof self) {
            return;
        }

        self::$instance->flush();
        unregister_tick_function([self::$instance, 'onTick']);
        self::$instance = null;
    }

    public static function isEnabled(): bool
    {
        return self::$instance instanceof self;
    }

    public function onTick(): void
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

        $location = sprintf('%s:%d', $file, $line);

        // Skip duplicate consecutive locations
        if ($location === $this->lastLocation) {
            return;
        }

        $this->lastLocation = $location;

        $sourceLine = $this->getSourceLine($file, $line);
        $timestamp = date('Y-m-d\TH:i:s');

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

    public function flush(): void
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

    private function getSourceLine(string $file, int $line): string
    {
        try {
            if (!isset($this->fileCache[$file])) {
                if (!file_exists($file)) {
                    return '<file not found>';
                }

                $this->fileCache[$file] = new SplFileObject($file);
            }

            $fileObj = $this->fileCache[$file];
            $fileObj->seek($line - 1);
            $content = $fileObj->current();

            return is_string($content) ? trim($content) : '<invalid line>';
        } catch (Throwable) {
            return '<read error>';
        }
    }
}
