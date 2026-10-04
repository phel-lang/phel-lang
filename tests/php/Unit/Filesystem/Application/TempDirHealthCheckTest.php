<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Application;

use Phel\Filesystem\Application\TempDirHealthCheck;
use PHPUnit\Framework\TestCase;

use function function_exists;

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
        mkdir($this->tempDir, 0o700, true);

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

        mkdir($this->tempDir, 0o700, true);
        chmod($this->tempDir, 0o500);

        $healthCheck = new TempDirHealthCheck($this->tempDir);
        $status = $healthCheck->checkHealth();

        self::assertFalse($status->isHealthy());

        chmod($this->tempDir, 0755);
    }

    public function test_it_closes_a_temp_dir_other_users_can_open(): void
    {
        if (!function_exists('posix_geteuid')) {
            self::markTestSkipped('needs POSIX permissions');
        }

        mkdir($this->tempDir, 0o700, true);
        chmod($this->tempDir, 0o755);

        $status = new TempDirHealthCheck($this->tempDir)->checkHealth();
        clearstatcache(true, $this->tempDir);

        self::assertTrue($status->isHealthy());
        self::assertSame(0o700, fileperms($this->tempDir) & 0o777);
    }

    public function test_it_is_unhealthy_when_another_user_owns_the_temp_dir(): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() === 0 || fileowner('/usr') === posix_geteuid()) {
            self::markTestSkipped('needs a directory owned by another user');
        }

        $status = new TempDirHealthCheck('/usr')->checkHealth();

        self::assertFalse($status->isHealthy());
        self::assertStringContainsString('owned by another user', $status->message);
    }
}
