<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Container\Container;

final class CommandDependencyProvider extends AbstractDependencyProvider
{
    public const PHP_CONFIG_READER = 'PHP_CONFIG_READER';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::PHP_CONFIG_READER, static function (Container $container) {
            return $container->getLocator()->get(PhpConfigReader::class);
        });
    }
}
