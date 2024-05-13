<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;

final class FilesystemConfig extends AbstractConfig
{
    public const KEEP_GENERATED_TEMP_FILES = PhelConfig::KEEP_GENERATED_TEMP_FILES;

    public function shouldKeepGeneratedTempFiles(): bool
    {
        return (bool)$this->get(self::KEEP_GENERATED_TEMP_FILES, false);
    }
}
