<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\ScalarCoercion;

/**
 * @internal
 */
final class FilesystemConfig extends AbstractConfig
{
    public function shouldKeepGeneratedTempFiles(): bool
    {
        return (bool) $this->get(PhelConfig::KEEP_GENERATED_TEMP_FILES, false);
    }

    public function getTempDir(): string
    {
        $default = PhelConfig::defaultTempDir();

        return ScalarCoercion::toString($this->get(PhelConfig::TEMP_DIR, $default), $default);
    }
}
