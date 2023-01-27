<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

final class Files
{
    /** @var list<string> */
    private static array $files = [];

    public function __construct()
    {
    }

    public static function reset(): void
    {
        self::$files = [];
    }

    public static function addFile(string $file): void
    {
        self::$files[] = $file;
    }

    /**
     * Read all files and erase the internal list.
     *
     * @return list<string>
     */
    public static function readAll(): array
    {
        $files = self::$files;
        self::$files = [];

        return $files;
    }
}
