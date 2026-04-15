<?php

declare(strict_types=1);

namespace Phel\Shared;

use RuntimeException;

use function floor;
use function memory_get_peak_usage;
use function microtime;
use function sprintf;

final class ResourceUsageFormatter
{
    private const array SIZES = [
        'GB' => 1073741824,
        'MB' => 1048576,
        'KB' => 1024,
    ];

    public function resourceUsageSinceStartOfRequest(): string
    {
        if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            throw new RuntimeException(
                "Cannot determine time at which the request started because \$_SERVER['REQUEST_TIME_FLOAT'] is not available",
            );
        }

        $seconds = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];

        return sprintf(
            'Time: %s, Memory: %s',
            $this->formatDuration($seconds),
            $this->formatBytes(memory_get_peak_usage(true)),
        );
    }

    private function formatDuration(float $seconds): string
    {
        $totalMilliseconds = $seconds * 1000.0;
        $hours = (int) floor($totalMilliseconds / 3600000.0);
        $minutes = (int) floor($totalMilliseconds / 60000.0) % 60;
        $remaining = $totalMilliseconds - ((float) $hours * 3600000.0) - ((float) $minutes * 60000.0);
        $wholeSeconds = (int) floor($remaining / 1000.0);
        $milliseconds = (int) ($remaining - ((float) $wholeSeconds * 1000.0));

        $result = '';
        if ($hours > 0) {
            $result = sprintf('%02d:', $hours);
        }

        $result .= sprintf('%02d:%02d', $minutes, $wholeSeconds);

        if ($milliseconds > 0) {
            $result .= sprintf('.%03d', $milliseconds);
        }

        return $result;
    }

    private function formatBytes(int $bytes): string
    {
        foreach (self::SIZES as $unit => $value) {
            if ($bytes >= $value) {
                return sprintf('%.2f %s', $bytes / $value, $unit);
            }
        }

        return $bytes . ' byte' . ($bytes !== 1 ? 's' : '');
    }
}
