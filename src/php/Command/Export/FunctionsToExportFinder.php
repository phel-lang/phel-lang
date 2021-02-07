<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Command\Shared\NamespaceExtractorInterface;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Lang\Keyword;
use Phel\Lang\Table;
use Phel\Runtime\RuntimeInterface;

final class FunctionsToExportFinder implements FunctionsToExportFinderInterface
{
    private string $projectRootDir;
    private RuntimeInterface $runtime;
    private NamespaceExtractorInterface $nsExtractor;

    public function __construct(
        string $projectRootDir,
        RuntimeInterface $runtime,
        NamespaceExtractorInterface $nsExtractor
    ) {
        $this->projectRootDir = $projectRootDir;
        $this->runtime = $runtime;
        $this->nsExtractor = $nsExtractor;
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, list<FunctionToExport>>
     */
    public function findInPaths(array $paths): array
    {
        $this->loadAllNsFromPaths($paths);

        return $this->findAllFunctionsToExport();
    }

    private function loadAllNsFromPaths(array $paths): void
    {
        $namespaces = $this->getNamespacesFromPaths($paths);

        foreach ($namespaces as $namespace) {
            $this->runtime->loadNs($namespace);
        }
    }

    /**
     * @return array<string, list<FunctionToExport>>
     */
    private function findAllFunctionsToExport(): array
    {
        $functionsToExport = [];

        foreach ($GLOBALS['__phel'] as $ns => $functions) {
            foreach ($functions as $fnName => $fn) {
                if ($this->isExport($ns, $fnName)) {
                    $functionsToExport[$ns] ??= [];
                    $functionsToExport[$ns][] = new FunctionToExport($fn);
                }
            }
        }

        return $functionsToExport;
    }

    private function getNamespacesFromPaths(array $paths): array
    {
        if (empty($paths)) {
            return $this->nsExtractor->getNamespacesFromConfig($this->projectRootDir);
        }

        return array_map(
            fn (string $filename): string => $this->nsExtractor->getNamespaceFromFile($filename),
            $paths
        );
    }

    private function isExport(string $ns, string $fnName): bool
    {
        $meta = $GLOBALS['__phel_meta'][$ns][$fnName] ?? new Table();

        return (bool)($meta[new Keyword('export')] ?? false);
    }
}
