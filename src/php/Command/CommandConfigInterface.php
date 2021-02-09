<?php

declare(strict_types=1);

namespace Phel\Command;

interface CommandConfigInterface
{
    /**
     * @return list<string>
     */
    public function getExportDirectories(): array;

    /**
     * @return list<string>
     */
    public function getDefaultTestDirectories(): array;
}
