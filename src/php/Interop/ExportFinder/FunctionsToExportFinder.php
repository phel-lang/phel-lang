<?php

declare(strict_types=1);

namespace Phel\Interop\ExportFinder;

use Phel\Command\Shared\Exceptions\ExtractorException;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Runtime\RuntimeFacadeInterface;

final class FunctionsToExportFinder implements FunctionsToExportFinderInterface
{
    private RuntimeFacadeInterface $runtimeFacade;
    /** @var list<string> */
    private array $exportDirectories;

    public function __construct(
        RuntimeFacadeInterface $runtimeFacade,
        array $exportDirectories
    ) {
        $this->runtimeFacade = $runtimeFacade;
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

        $namespaceFromDirectories = $this->runtimeFacade
            ->getNamespacesFromDirectories($this->exportDirectories);

        foreach ($namespaceFromDirectories as $namespace) {
            $runtime->loadNs($namespace);
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
