<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\BuildConfigInterface;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Compile\CompiledTargetPathResolver;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Compile\SecondaryFileHarvester;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadClasspath;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use RuntimeException;

use function dirname;
use function filemtime;
use function sprintf;

final readonly class ProjectCompiler
{
    private CompiledTargetPathResolver $targetPathResolver;

    public function __construct(
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileCompilerInterface $fileCompiler,
        private CompilerFacadeInterface $compilerFacade,
        private CommandFacadeInterface $commandFacade,
        private EntryPointPhpFileInterface $entryPointPhpFile,
        private BuildConfigInterface $config,
        private ?SecondaryFileHarvester $secondaryFileHarvester = null,
    ) {
        $this->targetPathResolver = new CompiledTargetPathResolver($compilerFacade);
    }

    /**
     * @return list<CompiledFile>
     */
    public function compileProject(BuildOptions $buildOptions): array
    {

        $srcDirectories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        $dest = $this->commandFacade->getOutputDirectory();

        return $this->compileFromTo($srcDirectories, $dest, $buildOptions);
    }

    /**
     * @return list<CompiledFile>
     */
    private function compileFromTo(array $srcDirectories, string $dest, BuildOptions $buildOptions): array
    {
        // Initialize the GlobalEnvironment before loading cached files.
        // This ensures Phel::clear() is called before any definitions are registered,
        // preventing definitions from being lost when compilation is triggered later.
        $this->compilerFacade->initializeGlobalEnvironment();

        // Publish the classpath so runtime `(load ...)` emissions can resolve
        // sibling files during compile-time statement evaluation.
        LoadClasspath::publish($srcDirectories);

        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($srcDirectories);
        /** @var list<CompiledFile> $result */
        $result = [];
        foreach ($namespaceInformation as $info) {
            if ($this->shouldIgnoreNs($info)) {
                continue;
            }

            // Secondary `(in-ns ...)` files are pulled in by the primary's
            // `(load ...)` during build-time evaluation; that run caches
            // their compiled output. We relocate those cached files below
            // so we don't recompile them standalone, which would re-run
            // macro expansions against a partially-ready registry.
            if (!$info->isPrimaryDefinition()) {
                continue;
            }

            $targetFile = $dest . '/' . $this->targetPathResolver->resolve($info, $srcDirectories);
            $targetDir = dirname($targetFile);
            if (!file_exists($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
            }

            if ($this->canUseCache($buildOptions, $targetFile, $info)) {
                // Load cached file to register definitions and execute top-level expressions
                BuildFacade::enableBuildMode();
                ob_start();
                try {
                    /** @psalm-suppress UnresolvableInclude */
                    require_once $targetFile;
                } finally {
                    ob_end_clean();
                    BuildFacade::disableBuildMode();
                }

                continue;
            }

            $result[] = $this->fileCompiler->compileFile(
                $info->getFile(),
                $targetFile,
                $buildOptions->isSourceMapEnabled(),
            );

            touch($targetFile, $this->getFileMtime($info->getFile()));
        }

        $this->harvestSecondaries($namespaceInformation, $dest, $srcDirectories);

        if ($this->config->shouldCreateEntryPointPhpFile()) {
            $this->entryPointPhpFile->createFile();
        }

        return $result;
    }

    /**
     * @param list<NamespaceInformation> $namespaceInformation
     * @param list<string>               $srcDirectories
     */
    private function harvestSecondaries(array $namespaceInformation, string $dest, array $srcDirectories): void
    {
        if (!$this->secondaryFileHarvester instanceof SecondaryFileHarvester) {
            return;
        }

        foreach ($namespaceInformation as $info) {
            if ($info->isPrimaryDefinition()) {
                continue;
            }

            if ($this->shouldIgnoreNs($info)) {
                continue;
            }

            $this->secondaryFileHarvester->harvest($info, $dest, $srcDirectories);
        }
    }

    private function shouldIgnoreNs(NamespaceInformation $info): bool
    {
        foreach ($this->config->getPathsToIgnore() as $path) {
            if (str_contains($info->getFile(), $path)) {
                return true;
            }
        }

        return false;
    }

    private function canUseCache(
        BuildOptions $buildOptions,
        string $targetFile,
        NamespaceInformation $info,
    ): bool {
        if (!$buildOptions->isCacheEnabled()
            || !file_exists($targetFile)
            || $this->getFileMtime($targetFile) !== $this->getFileMtime($info->getFile())
        ) {
            return false;
        }

        foreach ($this->config->getPathsToAvoidCache() as $path) {
            if (str_contains($targetFile, $path)) {
                return false;
            }
        }

        return true;
    }

    private function getFileMtime(string $file): int
    {
        $mtime = filemtime($file);

        if ($mtime === false) {
            throw new RuntimeException(sprintf('Unable to read file modification time for "%s".', $file));
        }

        return $mtime;
    }
}
