<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

interface CommandIoInterface
{
    public function fileGetContents(string $path): string;
}
