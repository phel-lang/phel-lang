<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Cache;

final class GraphWriteStream
{
    public $context;

    public static int $graphWrites = 0;

    private $stream;

    private $directory;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $openedPath = $path;
        $this->stream = $this->native(static fn() => fopen($path, $mode));

        return $this->stream !== false;
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->stream, $count);
    }

    public function stream_write(string $data): int|false
    {
        return fwrite($this->stream, $data);
    }

    public function stream_eof(): bool
    {
        return feof($this->stream);
    }

    public function stream_close(): void
    {
        fclose($this->stream);
    }

    public function stream_flush(): bool
    {
        return fflush($this->stream);
    }

    public function stream_lock(int $operation): bool
    {
        return $operation === 0 || flock($this->stream, $operation);
    }

    public function stream_stat(): array|false
    {
        return fstat($this->stream);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return fseek($this->stream, $offset, $whence) === 0;
    }

    public function stream_tell(): int|false
    {
        return ftell($this->stream);
    }

    public function stream_set_option(): bool
    {
        return false;
    }

    public function url_stat(string $path): array|false
    {
        return $this->native(static fn(): array|false => @stat($path));
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return $this->native(static fn(): bool => mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) !== 0));
    }

    public function unlink(string $path): bool
    {
        return $this->native(static fn(): bool => unlink($path));
    }

    public function dir_opendir(string $path): bool
    {
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

    public function rename(string $from, string $to): bool
    {
        $renamed = $this->native(static fn(): bool => rename($from, $to));
        if ($renamed && basename($to) === '.gacela-scoped-cache-graph.php') {
            ++self::$graphWrites;
        }

        return $renamed;
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
