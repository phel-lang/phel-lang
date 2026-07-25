<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function sprintf;

/**
 * Shared file walking for the architecture tests, which all need "every PHP
 * file under a directory, keyed by its path relative to that directory".
 */
trait ScansPhpSourcesTrait
{
    /**
     * @param string $relativeDir directory relative to the repository root, e.g. `src/php`
     *
     * @return array<string, string> relative path => file contents
     */
    private function phpFilesIn(string $relativeDir): array
    {
        $baseDir = dirname(__DIR__, 4) . '/' . $relativeDir;
        self::assertDirectoryExists($baseDir);

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir)) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace($baseDir . '/', '', $file->getPathname());
            $files[$relative] = (string) file_get_contents($file->getPathname());
        }

        ksort($files);

        return $files;
    }

    /**
     * Every `use …;` import under $rootNamespace, with its 1-based line number.
     *
     * @return list<array{int, string}> [line, fully qualified name]
     */
    private function importsUnder(string $contents, string $rootNamespace): array
    {
        $pattern = sprintf(
            '/^use\s+(?:function\s+|const\s+)?(%s\\\\[\w\\\\]+)/',
            preg_quote($rootNamespace, '/'),
        );

        $imports = [];

        foreach (explode("\n", $contents) as $index => $line) {
            if (preg_match($pattern, $line, $matches) === 1) {
                $imports[] = [$index + 1, $matches[1]];
            }
        }

        return $imports;
    }
}
