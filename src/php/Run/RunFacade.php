<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFacade;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @method RunFactory getFactory()
 */
final class RunFacade extends AbstractFacade implements RunFacadeInterface
{
    /**
     * TODO: Refactor and make the ReplCommand instantiable.
     */
    public function getReplCommand(): ReplCommand
    {
        return $this->getFactory()->createReplCommand();
    }

    /**
     * TODO: Refactor and make the RunCommand instantiable.
     */
    public function getRunCommand(): RunCommand
    {
        return $this->getFactory()->createRunCommand();
    }

    public function runNamespace(string $namespace): void
    {
        $this->getFactory()
            ->createNamespaceRunner()
            ->run($namespace);
    }

    public function registerExceptionHandler(): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->registerExceptionHandler();
    }

    /**
     * @param list<string> $paths
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesFromPaths(array $paths): array
    {
        return $this->getFactory()
            ->createNamespaceCollector()
            ->getDependenciesFromPaths($paths);
    }

    public function evalFile(NamespaceInformation $info): void
    {
        $this->getFactory()
            ->getBuildFacade()
            ->evalFile($info->getFile());
    }

    /**
     * @return mixed The result of the executed code
     */
    public function eval(string $phelCode): mixed
    {
        return $this->getFactory()
            ->getCompilerFacade()
            ->eval($phelCode, new CompileOptions());
    }

    public function writeLocatedException(OutputInterface $output, CompilerException $e): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->writeLocatedException(
                $output,
                $e->getNestedException(),
                $e->getCodeSnippet()
            );
    }

    public function writeStackTrace(OutputInterface $output, Throwable $e): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->writeStackTrace($output, $e);
    }
}
