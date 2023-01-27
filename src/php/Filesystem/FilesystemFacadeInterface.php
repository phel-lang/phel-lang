<?php

declare(strict_types=1);

namespace Phel\Filesystem;

interface FilesystemFacadeInterface
{
    public function addFile(string $file): void;

    /**
     * @return list<string>
     */
    public function clearAll(): array;
}
