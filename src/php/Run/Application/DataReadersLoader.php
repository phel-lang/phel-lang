<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\Facade\BuildFacadeInterface;
use Throwable;

use function array_unique;
use function file_exists;
use function is_dir;
use function realpath;

/**
 * Auto-loads `data-readers.phel` from each source root.
 *
 * The file, if present, is expected to be a Phel namespace that calls
 * `(register-tag ...)` for each tag it wants to register. Files found in
 * earlier directories are evaluated first, so entries from later
 * directories override earlier registrations.
 *
 * A `data-readers.phel` file that depends on `phel\reader` relies on the
 * auto-loader evaluating reader first; callers are responsible for
 * sequencing the dependency bootstrap before calling `load()`.
 */
final readonly class DataReadersLoader
{
    public const string FILE_NAME = 'data-readers.phel';

    public function __construct(
        private BuildFacadeInterface $buildFacade,
    ) {}

    /**
     * @param list<string> $srcDirectories
     */
    public function load(array $srcDirectories): void
    {
        $files = $this->findDataReaderFiles($srcDirectories);
        if ($files === []) {
            return;
        }

        // `data-readers.phel` typically `(:require phel\reader)` so we
        // bootstrap the reader namespace first; missing reader is treated
        // as a no-op to keep the loader opt-in.
        try {
            $readerInfos = $this->buildFacade->getDependenciesForNamespace(
                $srcDirectories,
                ['phel\\reader', 'phel\\core'],
            );
        } catch (Throwable) {
            return;
        }

        foreach ($readerInfos as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }

        foreach ($files as $file) {
            $this->buildFacade->evalFile($file);
        }
    }

    /**
     * @param list<string> $srcDirectories
     *
     * @return list<string>
     */
    private function findDataReaderFiles(array $srcDirectories): array
    {
        $files = [];
        foreach ($this->uniqueDirectories($srcDirectories) as $dir) {
            $file = $dir . '/' . self::FILE_NAME;
            if (file_exists($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function uniqueDirectories(array $directories): array
    {
        $normalised = [];
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $real = realpath($dir);
            if ($real === false) {
                continue;
            }

            $normalised[] = $real;
        }

        return array_values(array_unique($normalised));
    }
}
