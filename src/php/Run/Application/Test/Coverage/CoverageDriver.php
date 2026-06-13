<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use function constant;
use function defined;
use function extension_loaded;
use function function_exists;

/**
 * Thin adapter over the loaded line-coverage extension (pcov preferred, else
 * xdebug). Extension functions are resolved through string-named variables so
 * the source needs neither the pcov nor xdebug stubs to analyze, and never
 * references a symbol that may be absent. `detect()` returns null when neither
 * extension is available, letting the caller emit an actionable error.
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

        if (extension_loaded(self::XDEBUG) && function_exists('xdebug_start_code_coverage')) {
            return new self(self::XDEBUG);
        }

        return null;
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
}
