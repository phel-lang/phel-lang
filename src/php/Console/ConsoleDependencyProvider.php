<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Interop\InteropFacade;
use Phel\Run\RunFacade;

final class ConsoleDependencyProvider extends AbstractDependencyProvider
{
    public const COMMANDS = 'COMMANDS';

    private const FACADE_INTEROP = 'FACADE_INTEROP';
    private const FACADE_FORMATTER = 'FACADE_FORMATTER';
    private const FACADE_RUN = 'FACADE_RUN';
    private const FACADE_BUILD = 'FACADE_BUILD';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCompiler($container);
        $this->addFacadeFormatter($container);
        $this->addFacadeRun($container);
        $this->addFacadeBuild($container);

        $this->addCommands($container);
    }


    private function addFacadeCompiler(Container $container): void
    {
        $container->set(self::FACADE_INTEROP, static function (Container $container) {
            return $container->getLocator()->get(InteropFacade::class);
        });
    }

    private function addFacadeFormatter(Container $container): void
    {
        $container->set(self::FACADE_FORMATTER, static function (Container $container) {
            return $container->getLocator()->get(FormatterFacade::class);
        });
    }

    private function addFacadeRun(Container $container): void
    {
        $container->set(self::FACADE_RUN, static function (Container $container) {
            return $container->getLocator()->get(RunFacade::class);
        });
    }

    private function addFacadeBuild(Container $container): void
    {
        $container->set(self::FACADE_BUILD, static function (Container $container) {
            return $container->getLocator()->get(BuildFacade::class);
        });
    }

    private function addCommands(Container $container): void
    {
        $container->set(self::COMMANDS, static fn () => [
            $container->get(self::FACADE_INTEROP)->getExportCommand(),
            $container->get(self::FACADE_FORMATTER)->getFormatCommand(),
            $container->get(self::FACADE_RUN)->getReplCommand(),
            $container->get(self::FACADE_RUN)->getRunCommand(),
            $container->get(self::FACADE_RUN)->getTestCommand(),
            $container->get(self::FACADE_BUILD)->getCompileCommand(),
        ]);
    }
}
