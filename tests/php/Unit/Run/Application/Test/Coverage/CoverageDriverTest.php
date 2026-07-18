<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test\Coverage;

use Phel\Run\Application\Test\Coverage\CoverageDriver;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
use function function_exists;
use function in_array;
use function is_array;

final class CoverageDriverTest extends TestCase
{
    public function test_detect_matches_loaded_extension_or_null(): void
    {
        $driver = CoverageDriver::detect();

        if (extension_loaded('pcov')) {
            self::assertInstanceOf(CoverageDriver::class, $driver);
            self::assertSame(CoverageDriver::PCOV, $driver->name());
        } elseif (extension_loaded('xdebug') && $this->xdebugCoverageModeActive()) {
            self::assertInstanceOf(CoverageDriver::class, $driver);
            self::assertSame(CoverageDriver::XDEBUG, $driver->name());
        } else {
            self::assertNull($driver, 'no driver without a usable coverage extension');
        }
    }

    public function test_unavailability_reason_names_the_missing_piece(): void
    {
        if (extension_loaded('xdebug') && !$this->xdebugCoverageModeActive()) {
            self::assertStringContainsString('XDEBUG_MODE=coverage', CoverageDriver::unavailabilityReason());
        } else {
            self::assertStringContainsString('neither pcov nor xdebug', CoverageDriver::unavailabilityReason());
        }
    }

    private function xdebugCoverageModeActive(): bool
    {
        if (!function_exists('xdebug_info')) {
            return true;
        }

        $info = 'xdebug_info';
        $modes = $info('mode');

        return is_array($modes) && in_array('coverage', $modes, true);
    }
}
