<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

interface FileIoInterface
{
    public function isWritable(string $tempDir): bool;
}
