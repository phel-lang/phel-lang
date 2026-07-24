<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ScalarCoercion;

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
        return (bool) $this->get(PhelConfig::ENABLE_INTERMEDIATE_CACHE, false);
    }

    /**
     * Shares `PhelProjectDirectory::resolveCacheDir()` with the build config,
     * so the intermediate-artifact cache lands in the same `<cacheDir>` the
     * rest of the build cache uses and is cleared along with it.
     */
    public function getCacheDir(): string
    {
        return PhelProjectDirectory::resolveCacheDir(
            $this->getAppRootDir(),
            ScalarCoercion::toString($this->get(PhelConfig::CACHE_DIR, '.phel/cache')),
            ScalarCoercion::toString($this->get(PhelConfig::PHEL_DIR, '')),
        );
    }
}
