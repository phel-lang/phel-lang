<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFactory;
use Phel\Api\ApiFacadeInterface;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Console\ConsoleFacadeInterface;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Application\EvalModeExecutor;
use Phel\Run\Application\NamespaceRunner;
use Phel\Run\Application\NamespacesLoader;
use Phel\Run\Domain\Repl\ColorStyle;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Repl\ReplCommandSystemIo;
use Phel\Run\Domain\Runner\NamespaceCollector;
use Phel\Run\Domain\Runner\NamespaceRunnerInterface;

/**
 * @method RunConfig getConfig()
 */
class RunFactory extends AbstractFactory
{
    public function createNamespaceRunner(): NamespaceRunnerInterface
    {
        return new NamespaceRunner(
            $this->getCommandFacade(),
            $this->getBuildFacade(),
        );
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_COMMAND);
    }

    public function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_BUILD);
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_COMPILER);
    }

    public function createNamespaceCollector(): NamespaceCollector
    {
        return new NamespaceCollector(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
        );
    }

    public function createColorStyle(): ColorStyleInterface
    {
        return ColorStyle::withStyles();
    }

    public function createPrinter(): PrinterInterface
    {
        return Printer::readableWithColor();
    }

    public function createReplCommandIo(): ReplCommandIoInterface
    {
        return new ReplCommandSystemIo(
            $this->getConfig()->getPhelReplHistory(),
            $this->getCommandFacade(),
            $this->getApiFacade(),
        );
    }

    public function createNamespacesLoader(): NamespacesLoader
    {
        return new NamespacesLoader(
            $this->getCommandFacade(),
            $this->getBuildFacade(),
        );
    }

    public function getApiFacade(): ApiFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_API);
    }

    public function getConsoleFacade(): ConsoleFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_CONSOLE);
    }

    public function createEvalModeExecutor(): EvalModeExecutor
    {
        return new EvalModeExecutor(
            $this->createReplCommandIo(),
            $this->createColorStyle(),
            $this->createPrinter(),
            $this->getCompilerFacade(),
        );
    }
}
