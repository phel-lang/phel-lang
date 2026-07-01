<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\ExportFinder;

use Phel;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\Keyword;
use Phel\Shared\Exceptions\CompiledCodeIsMalformedException;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\FileException;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\NamespaceInformation;

use function is_string;

final readonly class FunctionsToExportFinder implements FunctionsToExportFinderInterface
{
    /**
     * @param list<string> $exportDirs
     */
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
        private array $exportDirs,
    ) {}

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
            static fn(NamespaceInformation $info): string => $info->getNamespace(),
            $namespaceFromDirectories,
        );

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            [...$namespaces, 'phel.core'],
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }

    /**
     * Scans every loaded Phel namespace and collects the definitions whose metadata
     * carries `:export true`, grouped by namespace so the generator can emit one
     * wrapper class per namespace.
     *
     * @return array<string, list<FunctionToExport>>
     */
    private function findAllFunctionsToExport(): array
    {
        $functionsToExport = [];

        foreach (Phel::getNamespaces() as $ns) {
            foreach (Phel::getDefinitionInNamespace($ns) as $fnName => $fn) {
                if (!$fn instanceof FnInterface) {
                    continue;
                }

                $meta = $this->definitionMeta($ns, $fnName);
                if ($this->isExport($meta)) {
                    $functionsToExport[$ns] ??= [];
                    $functionsToExport[$ns][] = new FunctionToExport(
                        $fn,
                        $meta->find(Keyword::create('attr', 'php')),
                        $this->returnTag($meta),
                    );
                }
            }
        }

        return $functionsToExport;
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>
     */
    private function definitionMeta(string $ns, string $fnName): PersistentMapInterface
    {
        /** @var PersistentMapInterface<mixed, mixed> $meta */
        $meta = Phel::getDefinitionMetaData($ns, $fnName)
            ?? Phel::map();

        return $meta;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    private function isExport(PersistentMapInterface $meta): bool
    {
        return (bool) ($meta[Keyword::create('export')] ?? false);
    }

    /**
     * The compiler stores the fn's return-type `:tag` (declared or inferred) as a
     * plain string in the definition metadata.
     *
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    private function returnTag(PersistentMapInterface $meta): ?string
    {
        $tag = $meta->find(Keyword::create('tag'));

        return is_string($tag) && $tag !== '' ? $tag : null;
    }
}
