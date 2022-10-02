<?php

declare(strict_types=1);

namespace Phel\Build\Domain\IO;

interface FileIoInterface
{
    public function getContents(string $filename): string;
}
