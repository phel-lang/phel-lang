<?php

declare(strict_types=1);

namespace PhelTest\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait IsolatedBuildCacheTrait
{
    private string|false $cacheDirBeforeIsolation = false;

    #[Before(1)]
    protected function isolateBuildCache(): void
    {
        $this->cacheDirBeforeIsolation = getenv('PHEL_CACHE_DIR');
        putenv('PHEL_CACHE_DIR');
    }

    #[After(-1)]
    protected function restoreBuildCache(): void
    {
        putenv($this->cacheDirBeforeIsolation === false
            ? 'PHEL_CACHE_DIR'
            : 'PHEL_CACHE_DIR=' . $this->cacheDirBeforeIsolation);
    }
}
