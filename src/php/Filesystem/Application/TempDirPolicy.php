<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Shared\CurrentUser;
use Phel\Shared\Exceptions\FileException;

use function chmod;
use function clearstatcache;
use function dirname;
use function fileowner;
use function fileperms;

/**
 * The temp dir holds generated PHP that is `require`d, so it has to belong to
 * the current user, and no one else may be able to swap it: whoever can rename
 * an ancestor can replace the whole path. An ancestor passes when it belongs to
 * this user or root and no one else can write to it, unless it is sticky like
 * `/tmp`, where only an entry's owner may rename it.
 *
 * @internal
 */
final class TempDirPolicy
{
    /**
     * What makes `$dir` unsafe, or null. Windows has no POSIX owners, and its
     * temp dir is per user already.
     */
    public static function violation(string $dir): ?FileException
    {
        $uid = CurrentUser::id();
        if ($uid === null) {
            return null;
        }

        if (@fileowner($dir) !== $uid) {
            return FileException::directoryIsOwnedByAnotherUser($dir);
        }

        for ($ancestor = dirname($dir); ; $ancestor = dirname($ancestor)) {
            $owner = @fileowner($ancestor);
            $mode = (int) @fileperms($ancestor);
            $writableByOthers = ($mode & 0o022) !== 0 && ($mode & 0o1000) === 0;
            if (($owner !== $uid && $owner !== 0) || $writableByOthers) {
                return FileException::directoryCanBeReplacedByAnotherUser($ancestor);
            }

            if (dirname($ancestor) === $ancestor) {
                return null;
            }
        }
    }

    public static function isOpenToOthers(string $dir): bool
    {
        return CurrentUser::id() !== null && ((int) @fileperms($dir) & 0o077) !== 0;
    }

    public static function isWritableByOthers(string $dir): bool
    {
        return CurrentUser::id() !== null && ((int) @fileperms($dir) & 0o022) !== 0;
    }

    public static function closeToOthers(string $dir): void
    {
        @chmod($dir, 0o700);
        clearstatcache(true, $dir);
    }
}
