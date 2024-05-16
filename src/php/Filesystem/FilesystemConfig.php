<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;

final class FilesystemConfig extends AbstractConfig
{
    public function shouldKeepGeneratedTempFiles(): bool
    {
        return (bool)$this->get(PhelConfig::KEEP_GENERATED_TEMP_FILES, false);
    }
}
