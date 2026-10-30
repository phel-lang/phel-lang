<?php

declare(strict_types=1);

namespace PhelTest\Support;

final class CountingScanFileStream
{
    public mixed $context;

    public static int $directoryOpens = 0;

    public static int $stats = 0;

    private mixed $directory;

    public function url_stat(string $path, int $flags): array|false
    {
        ++self::$stats;

        return $this->native(static fn(): array|false => ($flags & STREAM_URL_STAT_LINK) !== 0 ? @lstat($path) : @stat($path));
    }

    public function dir_opendir(string $path): bool
    {
        ++self::$directoryOpens;
        $this->directory = $this->native(static fn() => opendir($path));

        return $this->directory !== false;
    }

    public function dir_readdir(): string|false
    {
        return readdir($this->directory);
    }

    public function dir_closedir(): bool
    {
        closedir($this->directory);

        return true;
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->directory);

        return true;
    }

    private function native(callable $operation): mixed
    {
        stream_wrapper_restore('file');
        try {
            return $operation();
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }
}
