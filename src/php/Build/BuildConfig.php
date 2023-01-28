<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractConfig;

/**
 * @method BuildConfig getConfig()
 */
final class BuildConfig extends AbstractConfig
{
    public const IGNORE_WHEN_COMPILING = 'ignore-when-compiling';

    /**
     * @return list<string>
     */
    public function getPathsToIgnoreWhenCompiling(): array
    {
        return $this->get(self::IGNORE_WHEN_COMPILING, []);
    }
}
