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
use function realpath;

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
     * temp dir is per user already. Elsewhere an unknown user fails closed.
     */
    public static function violation(string $dir): ?FileException
    {
        if (!CurrentUser::hasPosixOwners()) {
            return null;
        }

        return self::violationFor($dir, CurrentUser::id());
    }

    /**
     * {@see violation()} for a given uid, null when it could not be found.
     */
    public static function violationFor(string $dir, ?int $uid): ?FileException
    {
        if ($uid === null) {
            return FileException::currentUserIsUnknown($dir);
        }

        if (@fileowner($dir) !== $uid) {
            return FileException::directoryIsOwnedByAnotherUser($dir);
        }

        // Owner and mode follow a symlink, so the parents of its target need
        // the same check as the parents of the path that names it.
        $realDir = realpath($dir);
        if ($realDir === false) {
            return FileException::directoryCanBeReplacedByAnotherUser($dir);
        }

        return self::replaceableAncestor($dir, $uid) ?? self::replaceableAncestor($realDir, $uid);
    }

    public static function isOpenToOthers(string $dir): bool
    {
        return CurrentUser::hasPosixOwners() && ((int) @fileperms($dir) & 0o077) !== 0;
    }

    public static function isWritableByOthers(string $dir): bool
    {
        return CurrentUser::hasPosixOwners() && ((int) @fileperms($dir) & 0o022) !== 0;
    }

    public static function closeToOthers(string $dir): void
    {
        @chmod($dir, 0o700);
        clearstatcache(true, $dir);
    }

    private static function replaceableAncestor(string $dir, int $uid): ?FileException
    {
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
}
