<?php

declare(strict_types=1);

namespace Phel\Balance\Infrastructure\IO;

use Phel\Balance\Domain\Exception\BalanceSourceException;
use Phel\Balance\Domain\FileIoInterface;

use function file_get_contents;
use function file_put_contents;

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
        if (@file_put_contents($path, $contents) === false) {
            throw BalanceSourceException::cannotWrite($path);
        }
    }
}
