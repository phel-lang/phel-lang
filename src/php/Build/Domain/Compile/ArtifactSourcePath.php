<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\Domain\Cache\SourceFilePath;

/**
 * @internal
 */
final class ArtifactSourcePath
{
    /** @var array<string, string> */
    private static array $originalSources = [];

    private function __construct() {}

    /**
     * @param array<string, string> $sources
     *
     * @return array<string, string>
     */
    public static function setOriginalSources(array $sources): array
    {
        $previous = self::$originalSources;
        self::$originalSources = $sources;

        return $previous;
    }

    public static function resolve(string $file, bool $realFile = false): string
    {
        $file = SourceFilePath::normalize($file);
        $file = self::$originalSources[$file] ?? $file;

        return $realFile ? (realpath($file) ?: $file) : $file;
    }
}
