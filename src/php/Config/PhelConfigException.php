<?php

declare(strict_types=1);

namespace Phel\Config;

use Phel\Phel;
use RuntimeException;

final class PhelConfigException extends RuntimeException
{
    public static function wrongType(string $phelConfigPath): self
    {
        return new self(
            sprintf(
                'The "%s" must return an array or a PhelConfig object. Path: %s',
                Phel::PHEL_CONFIG_FILE_NAME,
                $phelConfigPath
            ),
        );
    }
}
