<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Container\Container;

final class CommandProvider extends AbstractProvider
{
    public const string PHP_CONFIG_READER = 'PHP_CONFIG_READER';

    #[Provides(self::PHP_CONFIG_READER)]
    public function phpConfigReader(Container $container): PhpConfigReader
    {
        return $container->getLocator()->getRequired(PhpConfigReader::class);
    }
}
