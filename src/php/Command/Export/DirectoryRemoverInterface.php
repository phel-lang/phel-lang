<?php

declare(strict_types=1);

namespace Phel\Command\Export;

interface DirectoryRemoverInterface
{
    public function removeDir(string $target): void;
}
