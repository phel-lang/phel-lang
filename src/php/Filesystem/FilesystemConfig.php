<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;

final class FilesystemConfig extends AbstractConfig
{
    /**
     * Whether generated temp files should be kept instead of cleaned up.
     * Defaults to false when KEEP_GENERATED_TEMP_FILES is not configured.
     */
    public function shouldKeepGeneratedTempFiles(): bool
    {
        return (bool) $this->get(PhelConfig::KEEP_GENERATED_TEMP_FILES, false);
    }

    /**
     * The configured temp directory, defaulting to sys_get_temp_dir()
     * when TEMP_DIR is not configured.
     */
    public function getTempDir(): string
    {
        return (string) $this->get(PhelConfig::TEMP_DIR, sys_get_temp_dir());
    }
}
