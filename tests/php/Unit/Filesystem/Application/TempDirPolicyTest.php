<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Application;

use Phel\Filesystem\Application\TempDirPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function chmod;
use function function_exists;
use function mkdir;
use function posix_geteuid;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

final class TempDirPolicyTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!function_exists('posix_geteuid')) {
            self::markTestSkipped('needs POSIX permissions');
        }

        $this->dir = sys_get_temp_dir() . '/phel-policy-' . uniqid('', true);
        mkdir($this->dir, 0o700);
    }

    protected function tearDown(): void
    {
        @rmdir($this->dir);
    }

    /**
     * @return iterable<string, array{int, bool, bool}>
     */
    public static function modes(): iterable
    {
        yield 'owner only' => [0o700, false, false];
        yield 'readable by others' => [0o755, true, false];
        yield 'writable by group' => [0o770, true, true];
        yield 'writable by everyone' => [0o777, true, true];
    }

    #[DataProvider('modes')]
    public function test_it_tells_open_from_writable_by_others(int $mode, bool $open, bool $writable): void
    {
        chmod($this->dir, $mode);

        self::assertSame($open, TempDirPolicy::isOpenToOthers($this->dir));
        self::assertSame($writable, TempDirPolicy::isWritableByOthers($this->dir));
    }

    public function test_an_unknown_user_fails_closed(): void
    {
        // A PHP without the posix extension that cannot create a probe file
        // does not know its uid; skipping the checks would trust any dir.
        $violation = TempDirPolicy::violationFor($this->dir, null);

        self::assertNotNull($violation);
        self::assertStringContainsString('Cannot tell which user runs PHP', $violation->getMessage());
    }

    public function test_a_dir_the_current_user_owns_passes(): void
    {
        self::assertNull(TempDirPolicy::violationFor($this->dir, posix_geteuid()));
    }
}
