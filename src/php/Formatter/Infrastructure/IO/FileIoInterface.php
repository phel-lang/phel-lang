<?php

declare(strict_types=1);

namespace Phel\Formatter\Infrastructure\IO;

use Phel\Formatter\Domain\Exception\FilePathException;

interface FileIoInterface
{
    /**
     * @throws FilePathException
     */
    public function checkIfValid(string $filename): void;

    public function getContents(string $filename): string;

    public function putContents(string $filename, string $data): void;
}
