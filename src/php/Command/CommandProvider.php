<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Container\Container;
use Gacela\Framework\ServiceResolver\ServiceMap;

/**
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: CommandConfig::class)]
final class CommandProvider extends AbstractProvider
{
    /**
     * Keyed by a constant, not by `PhpConfigReader::class`.
     *
     * The class-string form is only safe when the published id differs from the
     * id the body resolves: the facade providers publish `*FacadeInterface` and
     * resolve the concrete facade. Here there is no separate interface, so
     * publishing the class the body asks the container for makes the binding
     * re-enter itself until the stack runs out.
     */
    public const string PHP_CONFIG_READER = 'PHP_CONFIG_READER';

    #[Provides(self::PHP_CONFIG_READER)]
    public function phpConfigReader(Container $container): PhpConfigReader
    {
        return $container->getLocator()->getRequired(PhpConfigReader::class);
    }
}
