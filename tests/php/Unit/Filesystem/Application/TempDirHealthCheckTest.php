<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Application;

use Phel\Filesystem\Application\TempDirHealthCheck;
use PHPUnit\Framework\TestCase;

final class TempDirHealthCheckTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-health-check-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function test_it_is_healthy_when_temp_dir_exists_and_is_writable(): void
    {
        mkdir($this->tempDir, 0777, true);

        $healthCheck = new TempDirHealthCheck($this->tempDir);
        $status = $healthCheck->checkHealth();

        self::assertTrue($status->isHealthy());
    }

    public function test_it_bootstraps_missing_temp_dir_and_reports_healthy(): void
    {
        self::assertDirectoryDoesNotExist($this->tempDir);

        $healthCheck = new TempDirHealthCheck($this->tempDir);
        $status = $healthCheck->checkHealth();

        self::assertTrue($status->isHealthy(), $status->message);
        self::assertDirectoryExists($this->tempDir);
    }

    public function test_it_is_unhealthy_when_temp_dir_cannot_be_created(): void
    {
        $nonCreatableDir = '/root/phel-health-check-no-permission-' . uniqid('', true);

        // Skip test if running as root (where all dirs are creatable)
        if (posix_getuid() === 0) {
            self::markTestSkipped('Cannot test permission failures when running as root.');
        }

        $healthCheck = new TempDirHealthCheck($nonCreatableDir);
        $status = $healthCheck->checkHealth();

        self::assertFalse($status->isHealthy());
    }

    public function test_it_is_unhealthy_when_temp_dir_is_not_writable(): void
    {
        // Skip test if running as root (where all dirs are writable)
        if (posix_getuid() === 0) {
            self::markTestSkipped('Cannot test permission failures when running as root.');
        }

        mkdir($this->tempDir, 0555, true);

        $healthCheck = new TempDirHealthCheck($this->tempDir);
        $status = $healthCheck->checkHealth();

        self::assertFalse($status->isHealthy());

        chmod($this->tempDir, 0755);
    }
}
