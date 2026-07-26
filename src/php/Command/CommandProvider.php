<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Container\Container;

/**
 * @internal
 */
final class CommandProvider extends AbstractProvider
{
    #[Provides(PhpConfigReader::class)]
    public function phpConfigReader(Container $container): PhpConfigReader
    {
        return $container->getLocator()->getRequired(PhpConfigReader::class);
    }
}
