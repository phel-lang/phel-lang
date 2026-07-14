<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ScalarCoercion;
use Phel\Shared\WritableCacheDir;

use function is_string;

final class CompilerConfig extends AbstractConfig
{
    public function assertsEnabled(): bool
    {
        return (bool) $this->get(PhelConfig::ASSERTS_ENABLED, true);
    }

    public function warnDeprecationsEnabled(): bool
    {
        return (bool) $this->get(PhelConfig::WARN_DEPRECATIONS, false);
    }

    public function isIntermediateCacheEnabled(): bool
    {
        return (bool) $this->get(PhelConfig::ENABLE_INTERMEDIATE_CACHE, false)
            && WritableCacheDir::isUsable($this->getCacheDir());
    }

    /**
     * Mirrors {@see \Phel\Build\BuildConfig::getCacheDir()} so the
     * intermediate-artifact cache lands in the same `<cacheDir>` the rest of
     * the build cache uses and is cleared along with it.
     */
    public function getCacheDir(): string
    {
        $envOverride = getenv('PHEL_CACHE_DIR');
        if (is_string($envOverride) && $envOverride !== '') {
            return $envOverride;
        }

        $cacheDir = ScalarCoercion::toString($this->get(PhelConfig::CACHE_DIR, '.phel/cache'));
        $phelDir = ScalarCoercion::toString($this->get(PhelConfig::PHEL_DIR, ''));

        return PhelProjectDirectory::resolve($this->getAppRootDir(), $cacheDir, $phelDir);
    }
}
