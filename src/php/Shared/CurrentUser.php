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

/**
 * @internal
 */
final class CurrentUser
{
    private static ?int $id = null;

    private static bool $resolved = false;

    /**
     * The effective uid, or null on Windows, which has no POSIX owners.
     * PHP built without the posix extension (common in Alpine images) still
     * gets one, from the owner of a file it creates.
     */
    public static function id(): ?int
    {
        if (!self::$resolved) {
            self::$id = self::resolve();
            self::$resolved = true;
        }

        return self::$id;
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
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }

        return self::ownerOfANewFile();
    }
}
