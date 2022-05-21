<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Interop\InteropFacade;
use Phel\Printer\Printer;
use Phel\Run\Domain\Repl\ColorStyle;
use Phel\Run\Domain\Repl\ReplCommandSystemIo;

/**
 * @method RunConfig getConfig()
 */
class RunDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMMAND = 'FACADE_COMMAND';
    public const FACADE_COMPILER = 'FACADE_COMPILER';
    public const FACADE_FORMATTER = 'FACADE_FORMATTER';
    public const FACADE_INTEROP = 'FACADE_INTEROP';
    public const FACADE_BUILD = 'FACADE_BUILD';
    public const PRINTER = 'PRINTER';
    public const COLOR_STYLE = 'COLOR_STYLE';
    public const REPL_COMMAND_IO = 'REPL_COMMAND_IO';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCommand($container);
        $this->addFacadeCompiler($container);
        $this->addFacadeFormatter($container);
        $this->addFacadeInterop($container);
        $this->addFacadeBuild($container);
        $this->addPrinter($container);
        $this->addColorStyle($container);
        $this->addReplCommandIo($container);
    }

    protected function addPrinter(Container $container): void
    {
        $container->set(self::PRINTER, static fn () => Printer::nonReadableWithColor());
    }

    protected function addColorStyle(Container $container): void
    {
        $container->set(self::COLOR_STYLE, static fn () => ColorStyle::withStyles());
    }

    protected function addReplCommandIo(Container $container): void
    {
        $replCommandIo = new ReplCommandSystemIo(
            $this->getConfig()->getPhelReplHistory(),
            $container->get(self::FACADE_COMMAND)
        );

        $container->set(self::REPL_COMMAND_IO, static fn () => $replCommandIo);
    }

    private function addFacadeCommand(Container $container): void
    {
        $container->set(self::FACADE_COMMAND, static function (Container $container) {
            return $container->getLocator()->get(CommandFacade::class);
        });
    }

    private function addFacadeCompiler(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, static function (Container $container) {
            return $container->getLocator()->get(CompilerFacade::class);
        });
    }

    private function addFacadeFormatter(Container $container): void
    {
        $container->set(self::FACADE_FORMATTER, static function (Container $container) {
            return $container->getLocator()->get(FormatterFacade::class);
        });
    }

    private function addFacadeInterop(Container $container): void
    {
        $container->set(self::FACADE_INTEROP, static function (Container $container) {
            return $container->getLocator()->get(InteropFacade::class);
        });
    }

    private function addFacadeBuild(Container $container): void
    {
        $container->set(self::FACADE_BUILD, static function (Container $container) {
            return $container->getLocator()->get(BuildFacade::class);
        });
    }
}
