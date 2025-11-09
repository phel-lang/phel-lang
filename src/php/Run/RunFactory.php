<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFactory;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Application\EvalExecutor;
use Phel\Run\Application\NamespaceLoader;
use Phel\Run\Application\NamespaceRunner;
use Phel\Run\Application\NamespacesLoader;
use Phel\Run\Domain\Repl\ColorStyle;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Repl\ReplCommandSystemIo;
use Phel\Run\Domain\Runner\NamespaceCollector;
use Phel\Run\Domain\Runner\NamespaceRunnerInterface;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\ConsoleFacadeInterface;

/**
 * @extends AbstractFactory<RunConfig>
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

    public function createEvalExecutor(): EvalExecutor
    {
        return new EvalExecutor(
            $this->createReplCommandIo(),
            $this->createColorStyle(),
            $this->createPrinter(),
            $this->getCompilerFacade(),
        );
    }

    public function createNamespaceLoader(): NamespaceLoader
    {
        return new NamespaceLoader(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
            $this->getConfig()->getReplStartupFile(),
        );
    }
}
