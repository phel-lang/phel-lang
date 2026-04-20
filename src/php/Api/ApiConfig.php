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
            'phel\\async',
            'phel\\base64',
            'phel\\cli',
            'phel\\core',
            'phel\\html',
            'phel\\http',
            'phel\\json',
            'phel\\match',
            'phel\\mock',
            'phel\\pprint',
            'phel\\reader',
            'phel\\repl',
            'phel\\router',
            'phel\\schema',
            'phel\\string',
            'phel\\test',
            'phel\\test\\gen',
            'phel\\test\\rose',
            'phel\\test\\selector',
            'phel\\test\\shrink',
            'phel\\walk',
            'phel\\watch',
        ];
    }
}
