<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Cache;

use function implode;
use function md5;
use function sort;

/**
 * What a cached lint result depends on besides the file itself: the shape
 * of a cached entry, the Phel release, whose analyzer can start reporting something new (#3478), the
 * registered rule codes, and the resolved settings.
 *
 * @internal
 */
final class LintCacheFingerprint
{
    /**
     * Bump when a cached diagnostic gains a field, so entries written without
     * it are recomputed instead of read back with the field empty.
     */
    private const int ENTRY_FORMAT = 2;

    /**
     * @param list<string> $ruleCodes
     */
    public static function of(string $phelVersion, array $ruleCodes, string $settingsFingerprint): string
    {
        sort($ruleCodes);

        return md5(self::ENTRY_FORMAT . '|' . $phelVersion . '|' . implode('|', $ruleCodes) . '|' . $settingsFingerprint);
    }
}
