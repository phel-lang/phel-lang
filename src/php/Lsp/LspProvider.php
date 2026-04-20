<?php

declare(strict_types=1);

namespace Phel\Lsp;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Api\ApiFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Lint\LintFacade;
use Phel\Run\RunFacade;

final class LspProvider extends AbstractProvider
{
    public const string FACADE_API = 'FACADE_API';

    public const string FACADE_LINT = 'FACADE_LINT';

    public const string FACADE_FORMATTER = 'FACADE_FORMATTER';

    public const string FACADE_COMPILER = 'FACADE_COMPILER';

    public const string FACADE_COMMAND = 'FACADE_COMMAND';

    public const string FACADE_RUN = 'FACADE_RUN';

    #[Provides(self::FACADE_API)]
    public function apiFacade(Container $container): ApiFacade
    {
        return $container->getLocator()->getRequired(ApiFacade::class);
    }

    #[Provides(self::FACADE_LINT)]
    public function lintFacade(Container $container): LintFacade
    {
        return $container->getLocator()->getRequired(LintFacade::class);
    }

    #[Provides(self::FACADE_FORMATTER)]
    public function formatterFacade(Container $container): FormatterFacade
    {
        return $container->getLocator()->getRequired(FormatterFacade::class);
    }

    #[Provides(self::FACADE_COMPILER)]
    public function compilerFacade(Container $container): CompilerFacade
    {
        return $container->getLocator()->getRequired(CompilerFacade::class);
    }

    #[Provides(self::FACADE_COMMAND)]
    public function commandFacade(Container $container): CommandFacade
    {
        return $container->getLocator()->getRequired(CommandFacade::class);
    }

    #[Provides(self::FACADE_RUN)]
    public function runFacade(Container $container): RunFacade
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }
}
