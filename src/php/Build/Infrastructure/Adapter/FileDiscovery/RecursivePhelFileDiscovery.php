<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Adapter\FileDiscovery;

use Phel\Build\Domain\Port\FileDiscovery\PhelFileDiscoveryPort;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function is_dir;

/**
 * Infrastructure adapter for discovering Phel files using PHP's recursive directory iterator.
 */
final readonly class RecursivePhelFileDiscovery implements PhelFileDiscoveryPort
{
    private const string PHEL_FILE_PATTERN = '/^.+\.phel$/i';

    public function findPhelFiles(array $directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            $realpath = $this->resolvePath($directory);
            if ($realpath === null) {
                continue;
            }

            if (!is_dir($realpath)) {
                continue;
            }

            $files = [...$files, ...$this->findFilesInDirectory($realpath)];
        }

        return array_values(array_unique($files));
    }

    public function resolvePath(string $path): ?string
    {
        // Support PHAR paths
        if (str_starts_with($path, 'phar://')) {
            return $path;
        }

        // Normal file system
        $real = realpath($path);

        return $real !== false ? $real : null;
    }

    /**
     * @return list<string>
     */
    private function findFilesInDirectory(string $directory): array
    {
        try {
            $directoryIterator = new RecursiveDirectoryIterator($directory);
            $iterator = new RecursiveIteratorIterator($directoryIterator);
            $phelIterator = new RegexIterator($iterator, self::PHEL_FILE_PATTERN, RegexIterator::GET_MATCH);

            $files = [];
            foreach ($phelIterator as $file) {
                $resolvedFile = $this->resolvePath($file[0]);
                if ($resolvedFile !== null) {
                    $files[] = $resolvedFile;
                }
            }

            return $files;
        } catch (UnexpectedValueException) {
            // Skip directories that cannot be read (e.g., permission denied)
            return [];
        }
    }
}
