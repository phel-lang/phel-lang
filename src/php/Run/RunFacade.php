<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFacade;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function dirname;

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

    public function runFile(string $filename): void
    {
        $namespace = $this->getNamespaceFromFile($filename)->getNamespace();

        $directories = [
            dirname($filename),
            ...$this->getFactory()->getCommandFacade()->getSourceDirectories(),
            ...$this->getFactory()->getCommandFacade()->getVendorSourceDirectories(),
        ];

        $infos = $this->getDependenciesForNamespace($directories, [$namespace, 'phel\\core']);
        foreach ($infos as $info) {
            $this->evalFile($info);
        }
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

    /**
     * @return list<NamespaceInformation>
     */
    public function getLoadedNamespaces(): array
    {
        return $this->getFactory()
            ->createNamespacesLoader()
            ->getLoadedNamespaces();
    }
}
