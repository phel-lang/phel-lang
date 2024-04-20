<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFacade;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Transpiler\Domain\Exceptions\TranspilerException;
use Phel\Transpiler\Infrastructure\TranspileOptions;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @method RunFactory getFactory()
 */
final class RunFacade extends AbstractFacade implements RunFacadeInterface
{
    public function runNamespace(string $namespace): void
    {
        $this->getFactory()
            ->createNamespaceRunner()
            ->run($namespace);
    }

    public function getNamespaceFromFile(string $fileOrPath): NamespaceInformation
    {
        return $this->getFactory()
            ->getBuildFacade()
            ->getNamespaceFromFile($fileOrPath);
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

    /**
     * @param list<string> $directories
     * @param list<string> $ns
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        return $this->getFactory()
            ->getBuildFacade()
            ->getDependenciesForNamespace($directories, $ns);
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
    public function eval(string $phelCode, TranspileOptions $compileOptions): mixed
    {
        return $this->getFactory()
            ->getTranspilerFacade()
            ->eval($phelCode, $compileOptions);
    }

    public function writeLocatedException(OutputInterface $output, TranspilerException $e): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->writeLocatedException(
                $output,
                $e->getNestedException(),
                $e->getCodeSnippet(),
            );
    }

    public function writeStackTrace(OutputInterface $output, Throwable $e): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->writeStackTrace($output, $e);
    }

    /**
     * @return list<string>
     */
    public function getAllPhelDirectories(): array
    {
        return $this->getFactory()
            ->getCommandFacade()
            ->getAllPhelDirectories();
    }
}
