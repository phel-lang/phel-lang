<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use function fgets;
use function file_exists;
use function file_get_contents;
use function getenv;
use function is_resource;
use function is_string;
use function max;
use function min;
use function pclose;
use function popen;
use function preg_match_all;
use function trim;

/**
 * Cross-platform detector for "how many parallel test workers should we spawn?".
 *
 * Fallback chain:
 *   1. Env var PHEL_TEST_WORKERS (if a positive integer)
 *   2. `nproc` on PATH
 *   3. `sysctl -n hw.ncpu` (macOS/BSD)
 *   4. /proc/cpuinfo line count (Linux without nproc)
 *   5. Hardcoded fallback of 4
 *
 * `detect()` clamps to {@see DEFAULT_CAP} so default `--parallel=auto`
 * runs stay friendly to laptops and shared CI runners. `detectMax()`
 * skips the cap for `--parallel=max` (power-user opt-in: use every
 * core the kernel reports).
 *
 * An explicit `PHEL_TEST_WORKERS` env var always overrides both paths
 * and ignores the cap.
 */
final class CpuCountDetector
{
    public const int DEFAULT_CAP = 8;

    public function detect(): int
    {
        $override = $this->envOverride();
        if ($override !== null) {
            return $override;
        }

        return max(1, min($this->detectFromSystem(), self::DEFAULT_CAP));
    }

    public function detectMax(): int
    {
        $override = $this->envOverride();
        if ($override !== null) {
            return $override;
        }

        return max(1, $this->detectFromSystem());
    }

    private function envOverride(): ?int
    {
        $value = $this->parseInt(getenv('PHEL_TEST_WORKERS'));
        if ($value === null) {
            return null;
        }

        return max(1, $value);
    }

    private function detectFromSystem(): int
    {
        $nproc = $this->parseInt($this->shellOut('nproc 2>/dev/null'));
        if ($nproc !== null && $nproc > 0) {
            return $nproc;
        }

        $sysctl = $this->parseInt($this->shellOut('sysctl -n hw.ncpu 2>/dev/null'));
        if ($sysctl !== null && $sysctl > 0) {
            return $sysctl;
        }

        $cpuinfo = $this->readCpuinfo();
        if ($cpuinfo > 0) {
            return $cpuinfo;
        }

        return 4;
    }

    private function shellOut(string $command): ?string
    {
        $handle = @popen($command, 'r');
        if (!is_resource($handle)) {
            return null;
        }

        $out = (string) fgets($handle);
        pclose($handle);

        $out = trim($out);
        return $out === '' ? null : $out;
    }

    private function readCpuinfo(): int
    {
        if (!file_exists('/proc/cpuinfo')) {
            return 0;
        }

        $contents = @file_get_contents('/proc/cpuinfo');
        if (!is_string($contents)) {
            return 0;
        }

        $count = preg_match_all('/^processor\s*:/m', $contents);

        return $count === false ? 0 : $count;
    }

    private function parseInt(string|false|null $raw): ?int
    {
        if (!is_string($raw)) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
