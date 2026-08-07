<?php

declare(strict_types=1);

namespace Phel\Lsp;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Api\ApiFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Lint\LintFacade;
use Phel\Run\RunFacade;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\FormatterFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: LspConfig::class)]
final class LspProvider extends AbstractProvider
{
    /**
     * `Lint` publishes no `*FacadeInterface`, so this cannot be keyed by
     * `LintFacade::class`: the body resolves that same id and the binding would
     * re-enter itself. See {@see CommandProvider} for the general rule.
     */
    public const string FACADE_LINT = 'FACADE_LINT';

    #[Provides(ApiFacadeInterface::class)]
    public function apiFacade(Container $container): ApiFacadeInterface
    {
        return $container->getLocator()->getRequired(ApiFacade::class);
    }

    #[Provides(self::FACADE_LINT)]
    public function lintFacade(Container $container): LintFacade
    {
        return $container->getLocator()->getRequired(LintFacade::class);
    }

    #[Provides(FormatterFacadeInterface::class)]
    public function formatterFacade(Container $container): FormatterFacadeInterface
    {
        return $container->getLocator()->getRequired(FormatterFacade::class);
    }

    #[Provides(RunFacadeInterface::class)]
    public function runFacade(Container $container): RunFacadeInterface
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }
}
