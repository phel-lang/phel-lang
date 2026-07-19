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
use Phel\Lang\LoadClasspath;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\NamespaceInformation;
use RuntimeException;

use function dirname;
use function filemtime;
use function sprintf;

final readonly class ProjectCompiler
{
    /**
     * Marker file in the output directory recording the optimization level of
     * the last build. The incremental cache only compares mtimes, so without
     * this record a level change would silently reuse stale output.
     */
    private const string OPTIMIZATION_LEVEL_FILE = '.phel-optimization-level';

    /**
     * Marker file recording that the last build stripped symbol meta. A
     * stripped target file must never be reused as cache by a non-strip
     * build (its `require_once` would register defs without meta, degrading
     * downstream inference), and vice versa — flipping the flag forces a
     * full recompile, same pattern as the optimization-level marker.
     */
    private const string STRIP_SYMBOL_META_FILE = '.phel-strip-symbol-meta';

    private CompiledTargetPathResolver $targetPathResolver;

    public function __construct(
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileCompilerInterface $fileCompiler,
        private CompilerFacadeInterface $compilerFacade,
        private CommandFacadeInterface $commandFacade,
        private EntryPointPhpFileInterface $entryPointPhpFile,
        private BuildConfigInterface $config,
        private SecondaryFileHarvester $secondaryFileHarvester,
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
     * @param list<string> $srcDirectories
     *
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

        $optimizationLevel = $buildOptions->getOptimizationLevel() ?? $this->config->getOptimizationLevel();
        $stripSymbolMeta = $this->config->shouldStripSymbolMeta();
        $optimizationLevelChanged = $this->storedOptimizationLevel($dest) !== $optimizationLevel
            || $this->storedStripSymbolMeta($dest) !== $stripSymbolMeta;

        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($srcDirectories);
        /** @var list<CompiledFile> $result */
        $result = [];
        // Dependency-ordered, so checking direct requires against this set
        // cascades transitively in one pass (see dependsOnRecompiled).
        /** @var array<string, true> $recompiledNamespaces */
        $recompiledNamespaces = [];
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

            if (!$optimizationLevelChanged
                && !$this->dependsOnRecompiled($info, $recompiledNamespaces)
                && $this->canUseCache($buildOptions, $targetFile, $info)
            ) {
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

                $result[] = new CompiledFile(
                    $info->getFile(),
                    $targetFile,
                    $info->getNamespace(),
                    cached: true,
                );

                continue;
            }

            $result[] = $this->fileCompiler->compileFile(
                $info->getFile(),
                $targetFile,
                $buildOptions->isSourceMapEnabled(),
                $optimizationLevel,
            );

            touch($targetFile, $this->getFileMtime($info->getFile()));
            $recompiledNamespaces[$info->getNamespace()] = true;
        }

        $this->storeOptimizationLevel($dest, $optimizationLevel);
        $this->storeStripSymbolMeta($dest, $stripSymbolMeta);
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
        return array_any($this->config->getPathsToIgnore(), static fn(string $path): bool => str_contains($info->getFile(), $path));
    }

    /**
     * Cache hits on mtime alone, so a dependent with an unchanged source but a
     * recompiled requirement must still recompile — else it keeps a stale macro
     * expansion baked in.
     *
     * @param array<string, true> $recompiledNamespaces
     */
    private function dependsOnRecompiled(NamespaceInformation $info, array $recompiledNamespaces): bool
    {
        return array_any(
            $info->getDependencies(),
            static fn(string $dependency): bool => isset($recompiledNamespaces[$dependency]),
        );
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

        return array_all($this->config->getPathsToAvoidCache(), static fn(string $path): bool => !str_contains($targetFile, $path));
    }

    private function storedOptimizationLevel(string $dest): int
    {
        $file = $dest . '/' . self::OPTIMIZATION_LEVEL_FILE;

        return is_file($file) ? (int) file_get_contents($file) : 0;
    }

    private function storeOptimizationLevel(string $dest, int $level): void
    {
        $file = $dest . '/' . self::OPTIMIZATION_LEVEL_FILE;

        if ($level === 0) {
            // Level 0 leaves no marker, keeping default builds byte-identical.
            if (is_file($file)) {
                @unlink($file);
            }

            return;
        }

        if (!is_dir($dest) && !mkdir($dest, 0777, true) && !is_dir($dest)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dest));
        }

        file_put_contents($file, (string) $level);
    }

    private function storedStripSymbolMeta(string $dest): bool
    {
        return is_file($dest . '/' . self::STRIP_SYMBOL_META_FILE);
    }

    private function storeStripSymbolMeta(string $dest, bool $strip): void
    {
        $file = $dest . '/' . self::STRIP_SYMBOL_META_FILE;

        if (!$strip) {
            // No marker for default builds, mirroring the optimization-level file.
            if (is_file($file)) {
                @unlink($file);
            }

            return;
        }

        if (!is_dir($dest) && !mkdir($dest, 0777, true) && !is_dir($dest)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dest));
        }

        file_put_contents($file, '1');
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
