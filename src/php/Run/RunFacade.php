<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFacade;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Infrastructure\CompileOptions;
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

    public function getDependenciesFromPaths(array $paths): array
    {
        return $this->getFactory()
            ->createNamespaceCollector()
            ->getDependenciesFromPaths($paths);
    }

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

    public function eval(string $phelCode, CompileOptions $compileOptions): mixed
    {
        return $this->getFactory()
            ->getCompilerFacade()
            ->eval($phelCode, $compileOptions);
    }

    public function writeLocatedException(OutputInterface $output, CompilerException $e): void
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

    public function getAllPhelDirectories(): array
    {
        return $this->getFactory()
            ->getCommandFacade()
            ->getAllPhelDirectories();
    }
}
