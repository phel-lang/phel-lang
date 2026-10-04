<?php

declare(strict_types=1);

namespace Phel\Shared;

use function fileowner;
use function function_exists;
use function is_int;
use function posix_geteuid;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_OS_FAMILY;

final class CurrentUser
{
    private static ?int $id = null;

    /**
     * The effective uid, or null when it is unknown: always on Windows, which
     * has no POSIX owners, and on another OS only when PHP has no posix
     * extension (common in Alpine images) and cannot create a probe file to
     * read the owner of. A failed probe is retried on the next call.
     */
    public static function id(): ?int
    {
        return self::$id ??= self::resolve();
    }

    public static function hasPosixOwners(): bool
    {
        return PHP_OS_FAMILY !== 'Windows';
    }

    public static function ownerOfANewFile(): ?int
    {
        $probe = @tempnam(sys_get_temp_dir(), 'phel-uid-');
        if ($probe === false) {
            return null;
        }

        $owner = @fileowner($probe);
        @unlink($probe);

        return is_int($owner) ? $owner : null;
    }

    private static function resolve(): ?int
    {
        if (!self::hasPosixOwners()) {
            return null;
        }

        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }

        return self::ownerOfANewFile();
    }
}
