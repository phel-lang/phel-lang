<?php

declare(strict_types=1);

namespace Phel\Nrepl\Infrastructure;

use RuntimeException;

use function file_put_contents;
use function is_dir;
use function is_file;
use function sprintf;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * The Clojure-standard `.nrepl-port` file. Editors (CIDER, Calva, Conjure)
 * look for it in the working directory to discover the port of a running
 * nREPL server. Written once the server is listening and removed when it
 * stops, so a stale file never points at a dead port.
 *
 * `delete()` only removes a file this instance wrote. A second server started
 * in the same directory overwrites the first one's file (as Clojure's does),
 * and a server that never got as far as writing must not remove the file
 * another one is still advertising.
 *
 * @internal
 */
final class NreplPortFile
{
    public const string FILE_NAME = '.nrepl-port';

    private bool $written = false;

    public function __construct(
        private readonly string $directory,
    ) {}

    public function path(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }

    public function write(int $port): void
    {
        if (!is_dir($this->directory)) {
            throw new RuntimeException(sprintf(
                'Cannot write %s: directory "%s" does not exist.',
                self::FILE_NAME,
                $this->directory,
            ));
        }

        $result = @file_put_contents($this->path(), (string) $port);
        if ($result === false) {
            throw new RuntimeException(sprintf(
                'Cannot write %s to "%s".',
                self::FILE_NAME,
                $this->directory,
            ));
        }

        $this->written = true;
    }

    public function delete(): void
    {
        if ($this->written && is_file($this->path())) {
            @unlink($this->path());
            $this->written = false;
        }
    }
}
