<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Command\Shared\Exceptions\ExtractorException;
use Phel\Command\Shared\NamespaceExtractorInterface;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Lang\Keyword;
use Phel\Lang\Table;
use Phel\Runtime\RuntimeInterface;

final class FunctionsToExportFinder implements FunctionsToExportFinderInterface
{
    private string $projectRootDir;
    private RuntimeInterface $runtime;
    private NamespaceExtractorInterface $nsExtractor;
    /** @var list<string> */
    private array $defaultDirectories;

    public function __construct(
        string $projectRootDir,
        RuntimeInterface $runtime,
        NamespaceExtractorInterface $nsExtractor,
        array $defaultDirectories
    ) {
        $this->projectRootDir = $projectRootDir;
        $this->runtime = $runtime;
        $this->nsExtractor = $nsExtractor;
        $this->defaultDirectories = $defaultDirectories;
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws ExtractorException
     * @throws FileException
     *
     * @return array<string, list<FunctionToExport>>
     */
    public function findInPaths(): array
    {
        $this->loadAllNsFromPaths();

        return $this->findAllFunctionsToExport();
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws ExtractorException
     * @throws FileException
     */
    private function loadAllNsFromPaths(): void
    {
        $namespaces = $this->nsExtractor
            ->getNamespacesFromDirectories($this->defaultDirectories, $this->projectRootDir);

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

    private function isExport(string $ns, string $fnName): bool
    {
        $meta = $GLOBALS['__phel_meta'][$ns][$fnName] ?? new Table();

        return (bool)($meta[new Keyword('export')] ?? false);
    }
}
