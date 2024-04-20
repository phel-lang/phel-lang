<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\ExportFinder;

use Phel\Build\BuildFacadeInterface;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Command\CommandFacadeInterface;
use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\TrarnspiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Exceptions\TranspilerException;

final readonly class FunctionsToExportFinder implements FunctionsToExportFinderInterface
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
        private array $exportDirs,
    ) {
    }

    /**
     *@throws FileException
     * @throws TranspilerException
     * @throws TrarnspiledCodeIsMalformedException
     * @throws ExtractorException
     *
     * @return array<string, list<FunctionToExport>>
     */
    public function findInPaths(): array
    {
        $this->loadAllNsFromPaths();

        return $this->findAllFunctionsToExport();
    }

    /**
     * @throws TranspilerException
     * @throws TrarnspiledCodeIsMalformedException
     * @throws ExtractorException
     * @throws FileException
     */
    private function loadAllNsFromPaths(): void
    {
        $this->commandFacade->registerExceptionHandler();

        $namespaceFromDirectories = $this->buildFacade
            ->getNamespaceFromDirectories($this->exportDirs);

        $namespaces = array_map(
            static fn (NamespaceInformation $info): string => $info->getNamespace(),
            $namespaceFromDirectories,
        );

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            [...$namespaces, 'phel\\core'],
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
