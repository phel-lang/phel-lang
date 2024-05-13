<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelBuildConfig;

/**
 * @method BuildConfig getConfig()
 */
final class BuildConfig extends AbstractConfig implements BuildConfigInterface
{
    public const IGNORE_WHEN_BUILDING = 'ignore-when-building';

    public const NO_CACHE_WHEN_BUILDING = 'no-cache-when-building';

    /**
     * @return list<string>
     */
    public function getPathsToIgnore(): array
    {
        return $this->get(self::IGNORE_WHEN_BUILDING, []);
    }

    /**
     * @return list<string>
     */
    public function getPathsToAvoidCache(): array
    {
        return $this->get(self::NO_CACHE_WHEN_BUILDING, []);
    }

    public function shouldCreateEntryPointPhpFile(): bool
    {
        return $this->getPhelOutConfig()->shouldCreateEntryPointPhpFile();
    }

    public function getPhelOutConfig(): PhelBuildConfig
    {
        return PhelBuildConfig::fromArray((array)$this->get('out', []));
    }
}
