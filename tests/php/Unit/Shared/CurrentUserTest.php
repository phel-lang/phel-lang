<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\CurrentUser;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function posix_geteuid;

use const PHP_OS_FAMILY;

final class CurrentUserTest extends TestCase
{
    public function test_without_posix_the_owner_of_a_new_file_is_the_effective_uid(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('posix_geteuid')) {
            self::markTestSkipped('Needs posix_geteuid to compare against.');
        }

        self::assertSame(posix_geteuid(), CurrentUser::ownerOfANewFile());
    }

    public function test_a_unix_host_has_a_uid(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Windows has no POSIX owners.');
        }

        self::assertIsInt(CurrentUser::id());
    }
}
