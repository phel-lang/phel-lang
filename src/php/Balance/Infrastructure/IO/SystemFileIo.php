<?php

declare(strict_types=1);

namespace Phel\Balance\Infrastructure\IO;

use Phel\Balance\Domain\Exception\BalanceSourceException;
use Phel\Balance\Domain\FileIoInterface;

use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function rename;
use function tempnam;
use function unlink;

/**
 * @internal
 */
final class SystemFileIo implements FileIoInterface
{
    public function read(string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw BalanceSourceException::cannotRead($path);
        }

        return $contents;
    }

    public function write(string $path, string $contents): void
    {
        $temporary = @tempnam(dirname($path), '.phel-balance-');
        if ($temporary === false || @file_put_contents($temporary, $contents) === false) {
            if ($temporary !== false) {
                @unlink($temporary);
            }

            throw BalanceSourceException::cannotWrite($path);
        }

        $permissions = @fileperms($path);
        if ($permissions !== false) {
            @chmod($temporary, $permissions & 0o7777);
        }

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw BalanceSourceException::cannotWrite($path);
        }
    }
}
