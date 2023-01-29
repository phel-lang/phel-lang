<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractConfig;

final class FilesystemConfig extends AbstractConfig
{
    public const KEEP_GENERATED_TEMP_FILES = 'keep-generated-temp-files';

    public function shouldKeepGeneratedTempFiles(): bool
    {
        return (bool)$this->get(self::KEEP_GENERATED_TEMP_FILES, false);
    }
}
