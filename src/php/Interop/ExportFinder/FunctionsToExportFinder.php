<?php

declare(strict_types=1);

namespace Phel\Interop\ExportFinder;

use Phel\Build\BuildFacadeInterface;
use Phel\Build\Extractor\ExtractorException;
use Phel\Build\Extractor\NamespaceInformation;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\TypeFactory;

final class FunctionsToExportFinder implements FunctionsToExportFinderInterface
{
    private BuildFacadeInterface $buildFacade;
    private CommandFacadeInterface $commandFacade;
    private array $exportDirs;

    public function __construct(
        BuildFacadeInterface $buildFacade,
        CommandFacadeInterface $commandFacade,
        array $exportDirs
    ) {
        $this->buildFacade = $buildFacade;
        $this->commandFacade = $commandFacade;
        $this->exportDirs = $exportDirs;
    }

    /**
     * @throws ExtractorException
     * @throws FileException
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
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
        $this->commandFacade->registerExceptionHandler();

        $namespaceFromDirectories = $this->buildFacade
            ->getNamespaceFromDirectories($this->exportDirs);

        $namespaces = array_map(
            static fn (NamespaceInformation $info) => $info->getNamespace(),
            $namespaceFromDirectories
        );

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            [...$namespaces, 'phel\\core']
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }

    /**
     * @return array<string, list<FunctionToExport>>
     */
    private function findAllFunctionsToExport(): array
    {
        $functionsToExport = [];

        foreach (Registry::getInstance()->getNamespaces() as $ns) {
            foreach (Registry::getInstance()->getDefinitionInNamespace($ns) as $fnName => $fn) {
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
        $meta = Registry::getInstance()->getDefinitionMetaData($ns, $fnName)
            ?? TypeFactory::getInstance()->emptyPersistentList();

        return (bool)($meta[Keyword::create('export')] ?? false);
    }
}
