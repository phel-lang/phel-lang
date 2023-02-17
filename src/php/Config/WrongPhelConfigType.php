<?php

declare(strict_types=1);

namespace Phel\Config;

use Phel\Phel;

final class WrongPhelConfigType
{
    public static function warning(string $phelConfigPath): void
    {
        $message = sprintf(
            'The "%s" must return an array or a PhelConfig object. Path: %s',
            Phel::PHEL_CONFIG_FILE_NAME,
            $phelConfigPath,
        );

        trigger_error($message);
    }
}
