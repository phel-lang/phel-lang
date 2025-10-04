<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\ExportFinder;

use Phel;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

final readonly class FunctionsToExportFinder implements FunctionsToExportFinderInterface
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
        private array $exportDirs,
    ) {
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

        foreach (Phel::getNamespaces() as $ns) {
            foreach (Phel::getDefinitionInNamespace($ns) as $fnName => $fn) {
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
        $meta = Phel::getDefinitionMetaData($ns, $fnName)
            ?? Phel::list();

        return (bool)($meta[Keyword::create('export')] ?? false);
    }
}
