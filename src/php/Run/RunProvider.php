<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Api\ApiFacade;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Console\ConsoleFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Interop\InteropFacade;

final class RunProvider extends AbstractProvider
{
    public const string FACADE_COMMAND = 'FACADE_COMMAND';

    public const string FACADE_COMPILER = 'FACADE_COMPILER';

    public const string FACADE_FORMATTER = 'FACADE_FORMATTER';

    public const string FACADE_INTEROP = 'FACADE_INTEROP';

    public const string FACADE_BUILD = 'FACADE_BUILD';

    public const string FACADE_API = 'FACADE_API';

    public const string FACADE_CONSOLE = 'FACADE_CONSOLE';

    #[Provides(self::FACADE_COMMAND)]
    public function commandFacade(Container $container): CommandFacade
    {
        return $container->getLocator()->getRequired(CommandFacade::class);
    }

    #[Provides(self::FACADE_COMPILER)]
    public function compilerFacade(Container $container): CompilerFacade
    {
        return $container->getLocator()->getRequired(CompilerFacade::class);
    }

    #[Provides(self::FACADE_FORMATTER)]
    public function formatterFacade(Container $container): FormatterFacade
    {
        return $container->getLocator()->getRequired(FormatterFacade::class);
    }

    #[Provides(self::FACADE_INTEROP)]
    public function interopFacade(Container $container): InteropFacade
    {
        return $container->getLocator()->getRequired(InteropFacade::class);
    }

    #[Provides(self::FACADE_BUILD)]
    public function buildFacade(Container $container): BuildFacade
    {
        return $container->getLocator()->getRequired(BuildFacade::class);
    }

    #[Provides(self::FACADE_API)]
    public function apiFacade(Container $container): ApiFacade
    {
        return $container->getLocator()->getRequired(ApiFacade::class);
    }

    #[Provides(self::FACADE_CONSOLE)]
    public function consoleFacade(Container $container): ConsoleFacade
    {
        return $container->getLocator()->getRequired(ConsoleFacade::class);
    }
}
