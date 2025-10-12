<?php

declare(strict_types=1);

namespace Phel\Formatter\Infrastructure\IO;

use Phel\Formatter\Domain\Exception\FilePathException;
use RuntimeException;

use function sprintf;

final class SystemFileIo implements FileIoInterface
{
    /**
     * @throws FilePathException
     */
    public function checkIfValid(string $filename): void
    {
        if (is_dir($filename)) {
            throw FilePathException::directoryFound($filename);
        }

        if (!is_file($filename)) {
            throw FilePathException::notFound($filename);
        }
    }

    public function getContents(string $filename): string
    {
        $contents = file_get_contents($filename);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read file "%s".', $filename));
        }

        return $contents;
    }

    public function putContents(string $filename, string $data): void
    {
        file_put_contents($filename, $data);
    }
}
