<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Cache;

use function implode;
use function md5;
use function sort;

/**
 * What a cached lint result depends on besides the file itself: the Phel
 * release, whose analyzer can start reporting something new (#3478), the
 * registered rule codes, and the resolved settings.
 *
 * @internal
 */
final class LintCacheFingerprint
{
    /**
     * @param list<string> $ruleCodes
     */
    public static function of(string $phelVersion, array $ruleCodes, string $settingsFingerprint): string
    {
        sort($ruleCodes);

        return md5($phelVersion . '|' . implode('|', $ruleCodes) . '|' . $settingsFingerprint);
    }
}
