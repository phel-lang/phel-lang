<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use RuntimeException;

use function dirname;
use function file_exists;
use function is_dir;
use function md5;
use function mkdir;
use function sprintf;

/**
 * Copies a compiled `(in-ns ...)` secondary from the compile cache into
 * the build output directory.
 *
 * Primary files compile directly through `FileCompiler`. Secondaries
 * are pulled in by the primary's `(load ...)` at build-time evaluation,
 * which leaves a cached `.php` in `CompiledCodeCache`. This class
 * relocates that cached output into the public build tree so a deployed
 * artifact ships a sibling `.php` for every `(in-ns ...)` file.
 */
final readonly class SecondaryFileHarvester
{
    public function __construct(
        private CompiledCodeCache $compiledCodeCache,
        private CompiledTargetPathResolver $targetPathResolver,
        private FileIoInterface $fileIo,
    ) {}

    /**
     * @param list<string> $sourceDirectories
     */
    public function harvest(NamespaceInformation $secondary, string $destDir, array $sourceDirectories): void
    {
        $sourceFile = $secondary->getFile();
        $sourceCode = $this->fileIo->getContents($sourceFile);
        $cachedPath = $this->compiledCodeCache->get($sourceFile, md5($sourceCode));

        if ($cachedPath === null) {
            // The primary didn't (load ...) this secondary during the build.
            // Compile cache may have been disabled or the secondary is
            // orphaned — nothing to copy, the build still succeeds.
            return;
        }

        $targetPath = $destDir . '/' . $this->targetPathResolver->resolve($secondary, $sourceDirectories);
        $this->ensureDir(dirname($targetPath));
        $this->fileIo->putContents($targetPath, $this->fileIo->getContents($cachedPath));
        $this->fileIo->putContents(
            (string) preg_replace('/\.php$/', '.phel', $targetPath),
            $sourceCode,
        );
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
