<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelOutConfig;

/**
 * @method BuildConfig getConfig()
 */
final class BuildConfig extends AbstractConfig
{
    public const IGNORE_WHEN_BUILDING = 'ignore-when-building';

    /**
     * @return list<string>
     */
    public function getPathsToIgnore(): array
    {
        return $this->get(self::IGNORE_WHEN_BUILDING, []);
    }

    public function getPhelOutConfig(): PhelOutConfig
    {
        return PhelOutConfig::fromArray((array)$this->get('out', []));
    }
}
