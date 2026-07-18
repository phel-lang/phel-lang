<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use function constant;
use function defined;
use function extension_loaded;
use function function_exists;
use function in_array;
use function is_array;

/**
 * Thin adapter over the loaded line-coverage extension (pcov preferred, else
 * xdebug). Extension functions are resolved through string-named variables so
 * the source needs neither the pcov nor xdebug stubs to analyze, and never
 * references a symbol that may be absent. `detect()` returns null when no
 * usable extension is available — xdebug counts only when `coverage` is among
 * its active modes, since `xdebug_start_code_coverage()` collects nothing
 * otherwise — letting the caller emit an actionable error via
 * `unavailabilityReason()`.
 */
final readonly class CoverageDriver
{
    public const string PCOV = 'pcov';

    public const string XDEBUG = 'xdebug';

    private function __construct(
        private string $driver,
    ) {}

    public static function detect(): ?self
    {
        if (extension_loaded(self::PCOV) && function_exists('pcov\\start')) {
            return new self(self::PCOV);
        }

        if (extension_loaded(self::XDEBUG)
            && function_exists('xdebug_start_code_coverage')
            && self::xdebugCoverageModeActive()
        ) {
            return new self(self::XDEBUG);
        }

        return null;
    }

    /**
     * Why `detect()` returned null, phrased as a hint the user can act on.
     */
    public static function unavailabilityReason(): string
    {
        if (extension_loaded(self::XDEBUG) && !self::xdebugCoverageModeActive()) {
            return "xdebug is loaded but 'coverage' is not an active mode; "
                . 're-run with XDEBUG_MODE=coverage or set xdebug.mode=coverage in php.ini.';
        }

        return 'neither pcov nor xdebug is loaded.';
    }

    public function name(): string
    {
        return $this->driver;
    }

    public function start(): void
    {
        if ($this->driver === self::PCOV) {
            $start = 'pcov\\start';
            $start();
            return;
        }

        $unused = defined('XDEBUG_CC_UNUSED') ? constant('XDEBUG_CC_UNUSED') : 1;
        $dead = defined('XDEBUG_CC_DEAD_CODE') ? constant('XDEBUG_CC_DEAD_CODE') : 2;
        $start = 'xdebug_start_code_coverage';
        $start($unused | $dead);
    }

    /**
     * Stops collection and returns raw coverage as
     * `[absolutePhpFile => [lineNumber => hitCount]]`. A positive hit count
     * means the line executed.
     *
     * @return array<string, array<int, int>>
     */
    public function stop(): array
    {
        if ($this->driver === self::PCOV) {
            $collect = 'pcov\\collect';
            $stop = 'pcov\\stop';
            $clear = 'pcov\\clear';
            /** @var array<string, array<int, int>> $data */
            $data = $collect();
            $stop();
            $clear();

            return $data;
        }

        $collect = 'xdebug_get_code_coverage';
        $stop = 'xdebug_stop_code_coverage';
        /** @var array<string, array<int, int>> $data */
        $data = $collect();
        $stop();

        return $data;
    }

    /**
     * Whether xdebug currently runs with the `coverage` mode enabled (via
     * `xdebug.mode` or the `XDEBUG_MODE` env override). Xdebug builds too old
     * to expose `xdebug_info()` cannot be queried; assume usable.
     */
    private static function xdebugCoverageModeActive(): bool
    {
        if (!function_exists('xdebug_info')) {
            return true;
        }

        $info = 'xdebug_info';
        $modes = $info('mode');

        return is_array($modes) && in_array('coverage', $modes, true);
    }
}
