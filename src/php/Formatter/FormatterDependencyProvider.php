<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;

final class FormatterDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';
    public const FACADE_COMMAND = 'FACADE_COMMAND';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCompiler($container);
        $this->addFacadeCommand($container);
    }

    private function addFacadeCompiler(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, function (Container $container) {
            return $container->getLocator()->get(CompilerFacade::class);
        });
    }

    private function addFacadeCommand(Container $container): void
    {
        $container->set(self::FACADE_COMMAND, function (Container $container) {
            return $container->getLocator()->get(CommandFacade::class);
        });
    }
}
