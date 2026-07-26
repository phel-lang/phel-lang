<?php

declare(strict_types=1);

namespace Phel\Lsp;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
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
final class LspProvider extends AbstractProvider
{
    #[Provides(ApiFacadeInterface::class)]
    public function apiFacade(Container $container): ApiFacadeInterface
    {
        return $container->getLocator()->getRequired(ApiFacade::class);
    }

    #[Provides(LintFacade::class)]
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
