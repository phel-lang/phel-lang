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
    /**
     * @param list<string> $importPaths
     */
    public function runNamespace(string $namespace, array $importPaths = []): void
    {
        $this->getFactory()
            ->createNamespaceRunner()
            ->run($namespace, $this->resolveImportPaths($importPaths));
    }

    /**
     * @param list<string> $importPaths
     */
    public function runFile(string $filename, array $importPaths = []): void
    {
        $namespace = $this->getNamespaceFromFile($filename)->getNamespace();

        $directories = [
            dirname($filename),
            ...$this->resolveImportPaths($importPaths, dirname($filename)),
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

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function resolveImportPaths(array $paths, ?string $base = null): array
    {
        $result = [];
        foreach ($paths as $path) {
            if (str_starts_with($path, 'phar://')) {
                $result[] = $path;
                continue;
            }

            $real = realpath($path);
            if ($real !== false) {
                $result[] = $real;
                continue;
            }

            $prefix = $base ?? getcwd() ?: '.';
            $joined = $prefix . '/' . $path;
            $resolved = realpath($joined);
            $result[] = $resolved !== false ? $resolved : $joined;
        }

        return array_values(array_unique($result));
    }
}
