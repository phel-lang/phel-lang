<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractConfig;

final class ApiConfig extends AbstractConfig
{
    /**
     * @return list<string>
     */
    public static function allNamespaces(): array
    {
        return [
            'phel\\base64',
            'phel\\core',
            'phel\\debug',
            'phel\\html',
            'phel\\http',
            'phel\\json',
            'phel\\mock',
            'phel\\repl',
            'phel\\str',
            'phel\\test',
        ];
    }
}
