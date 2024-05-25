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
            'phel\\core',
            'phel\\repl',
            'phel\\http',
            'phel\\html',
            'phel\\test',
            'phel\\json',
            'phel\\str',
        ];
    }
}
