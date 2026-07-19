<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\Domain\Cache\CompiledCodeCacheInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Shared\CompiledSourceHash;
use Phel\Shared\NamespaceInformation;
use Phel\Shared\SourceMap\SourceMapSiblings;
use RuntimeException;

use function dirname;
use function file_exists;
use function is_dir;
use function mkdir;
use function sprintf;

/**
 * Writes a compiled `(in-ns ...)` secondary into the build output directory.
 *
 * Primary files compile directly through `FileCompiler`. Secondaries are pulled
 * in by the primary's `(load ...)` at build-time evaluation; that run compiles
 * each one against a warm registry. This class lands the resulting `.php` next
 * to the primary so a deployed artifact (e.g. a self-contained PHAR) ships a
 * sibling for every `(in-ns ...)` file and can `(load ...)` it without the
 * `.phel` sources or a runtime compiler.
 *
 * It never recompiles a secondary standalone — that re-runs macro expansion
 * against a partially-ready registry and fails. It takes the build-time output
 * from the compiled-code cache when enabled, else from the in-memory
 * {@see CompiledSecondaryStore} the evaluator fills during the same build. With
 * the cache off and no store a build emitted the primary `out/phel/core.php`
 * but none of its `out/phel/core/*.php` secondaries, so the bundle fataled with
 * "Cannot locate core/… for (load ...)" on first load.
 */
final readonly class SecondaryFileHarvester
{
    public function __construct(
        private CompiledTargetPathResolver $targetPathResolver,
        private FileIoInterface $fileIo,
        private CompiledSecondaryStore $compiledSecondaryStore,
        private ?CompiledCodeCacheInterface $compiledCodeCache = null,
        private int $optimizationLevel = 0,
        private bool $stripSymbolMeta = false,
    ) {}

    /**
     * @param list<string> $sourceDirectories
     */
    public function harvest(NamespaceInformation $secondary, string $destDir, array $sourceDirectories): void
    {
        $sourceFile = $secondary->getFile();
        $sourceCode = $this->fileIo->getContents($sourceFile);

        $phpCode = $this->compiledPhp($sourceFile, $sourceCode);
        if ($phpCode === null) {
            // The primary never (load ...)ed this secondary during the build
            // (e.g. an orphaned file) — nothing to emit, the build still
            // succeeds.
            return;
        }

        $targetPath = $destDir . '/' . $this->targetPathResolver->resolve($secondary, $sourceDirectories);
        $this->ensureDir(dirname($targetPath));
        if ($this->stripSymbolMeta) {
            // Same artifact-only strip as FileCompiler: the cached/stored
            // code was evaluated with full meta during the build.
            $phpCode = SymbolMetaStripper::strip($phpCode);
        }

        $this->fileIo->putContents($targetPath, $phpCode);
        $this->fileIo->putContents(
            SourceMapSiblings::sourceFile($targetPath),
            $sourceCode,
        );
    }

    private function compiledPhp(string $sourceFile, string $sourceCode): ?string
    {
        if ($this->compiledCodeCache instanceof CompiledCodeCacheInterface) {
            // Must key identically to FileEvaluator's writer, or an -O>0 build
            // misses every secondary and ships a broken artifact (the #2449 mode).
            $cachedPath = $this->compiledCodeCache->get(
                $sourceFile,
                CompiledSourceHash::of($sourceCode, $this->optimizationLevel),
            );
            if ($cachedPath !== null) {
                return $this->fileIo->getContents($cachedPath);
            }
        }

        return $this->compiledSecondaryStore->get($sourceFile);
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!file_exists($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
    }
}
