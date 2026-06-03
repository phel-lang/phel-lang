<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\CpuCountDetector;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

final class CpuCountDetectorTest extends TestCase
{
    private ?string $originalEnv = null;

    protected function setUp(): void
    {
        $env = getenv('PHEL_TEST_WORKERS');
        $this->originalEnv = $env === false ? null : $env;
        putenv('PHEL_TEST_WORKERS');
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === null) {
            putenv('PHEL_TEST_WORKERS');
        } else {
            putenv('PHEL_TEST_WORKERS=' . $this->originalEnv);
        }
    }

    public function test_env_var_overrides_system_detection(): void
    {
        putenv('PHEL_TEST_WORKERS=3');

        self::assertSame(3, new CpuCountDetector()->detect());
    }

    public function test_env_var_above_cap_is_honoured(): void
    {
        putenv('PHEL_TEST_WORKERS=16');

        self::assertSame(16, new CpuCountDetector()->detect());
    }

    public function test_returns_at_least_one(): void
    {
        $count = new CpuCountDetector()->detect();

        self::assertGreaterThanOrEqual(1, $count);
    }

    public function test_capped_at_default_cap_without_env_override(): void
    {
        $count = new CpuCountDetector()->detect();

        self::assertLessThanOrEqual(CpuCountDetector::DEFAULT_CAP, $count);
    }

    public function test_detect_max_skips_the_default_cap(): void
    {
        $detector = new CpuCountDetector();
        $capped = $detector->detect();
        $uncapped = $detector->detectMax();

        self::assertGreaterThanOrEqual(1, $uncapped);
        self::assertGreaterThanOrEqual($capped, $uncapped);
    }

    public function test_detect_max_honours_env_override(): void
    {
        putenv('PHEL_TEST_WORKERS=12');

        self::assertSame(12, new CpuCountDetector()->detectMax());
    }

    public function test_invalid_env_var_falls_back_to_detection(): void
    {
        putenv('PHEL_TEST_WORKERS=notanumber');

        $count = new CpuCountDetector()->detect();

        self::assertGreaterThanOrEqual(1, $count);
        self::assertLessThanOrEqual(CpuCountDetector::DEFAULT_CAP, $count);
    }

    public function test_zero_env_var_is_clamped_to_one(): void
    {
        putenv('PHEL_TEST_WORKERS=0');

        self::assertSame(1, new CpuCountDetector()->detect());
        self::assertSame(1, new CpuCountDetector()->detectMax());
    }

    public function test_negative_env_var_falls_back_to_detection(): void
    {
        putenv('PHEL_TEST_WORKERS=-4');

        $count = new CpuCountDetector()->detect();

        self::assertGreaterThanOrEqual(1, $count);
        self::assertLessThanOrEqual(CpuCountDetector::DEFAULT_CAP, $count);
    }
}
