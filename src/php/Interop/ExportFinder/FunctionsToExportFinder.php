<?php

declare(strict_types=1);

namespace Phel\Interop\ExportFinder;

use Phel\Compiler\Compiler\ExtractorException;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Runtime\RuntimeFacadeInterface;

final class FunctionsToExportFinder implements FunctionsToExportFinderInterface
{
    private RuntimeFacadeInterface $runtimeFacade;
    private CompilerFacadeInterface $compilerFacade;
    /** @var list<string> */
    private array $exportDirectories;

    public function __construct(
        RuntimeFacadeInterface $runtimeFacade,
        CompilerFacadeInterface $compilerFacade,
        array $exportDirectories
    ) {
        $this->runtimeFacade = $runtimeFacade;
        $this->compilerFacade = $compilerFacade;
        $this->exportDirectories = $exportDirectories;
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws ExtractorException
     * @throws FileException
     * @throws CompilerException
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
        $runtime = $this->runtimeFacade->getRuntime();

        $namespaceFromDirectories = $this->compilerFacade
            ->extractNamespaceFromDirectories($this->exportDirectories);

        foreach ($namespaceFromDirectories as $namespaceNode) {
            $runtime->loadNs($namespaceNode->getNamespace());
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
        /** @var PersistentMapInterface $meta */
        $meta = $GLOBALS['__phel_meta'][$ns][$fnName] ?? TypeFactory::getInstance()->emptyPersistentList();

        return (bool)($meta[new Keyword('export')] ?? false);
    }
}
