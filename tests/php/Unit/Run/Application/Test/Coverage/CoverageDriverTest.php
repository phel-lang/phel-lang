<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test\Coverage;

use Phel\Run\Application\Test\Coverage\CoverageDriver;
use PHPUnit\Framework\TestCase;

use function extension_loaded;

final class CoverageDriverTest extends TestCase
{
    public function test_detect_matches_loaded_extension_or_null(): void
    {
        $driver = CoverageDriver::detect();

        if (extension_loaded('pcov') || extension_loaded('xdebug')) {
            self::assertInstanceOf(CoverageDriver::class, $driver);
            self::assertContains($driver->name(), [CoverageDriver::PCOV, CoverageDriver::XDEBUG]);
        } else {
            self::assertNull($driver, 'no driver when neither extension is loaded');
        }
    }
}
