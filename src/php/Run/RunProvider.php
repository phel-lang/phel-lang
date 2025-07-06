<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Api\ApiFacade;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
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

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCommand($container);
        $this->addFacadeCompiler($container);
        $this->addFacadeFormatter($container);
        $this->addFacadeInterop($container);
        $this->addFacadeBuild($container);
        $this->addFacadeApi($container);
    }

    private function addFacadeCommand(Container $container): void
    {
        $container->set(
            self::FACADE_COMMAND,
            static fn (Container $container) => $container->getLocator()->get(CommandFacade::class),
        );
    }

    private function addFacadeCompiler(Container $container): void
    {
        $container->set(
            self::FACADE_COMPILER,
            static fn (Container $container) => $container->getLocator()->get(CompilerFacade::class),
        );
    }

    private function addFacadeFormatter(Container $container): void
    {
        $container->set(
            self::FACADE_FORMATTER,
            static fn (Container $container) => $container->getLocator()->get(FormatterFacade::class),
        );
    }

    private function addFacadeInterop(Container $container): void
    {
        $container->set(
            self::FACADE_INTEROP,
            static fn (Container $container) => $container->getLocator()->get(InteropFacade::class),
        );
    }

    private function addFacadeBuild(Container $container): void
    {
        $container->set(
            self::FACADE_BUILD,
            static fn (Container $container) => $container->getLocator()->get(BuildFacade::class),
        );
    }

    private function addFacadeApi(Container $container): void
    {
        $container->set(
            self::FACADE_API,
            static fn (Container $container) => $container->getLocator()->get(ApiFacade::class),
        );
    }
}
