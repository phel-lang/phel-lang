<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

interface WatchFileScannerInterface
{
    /**
     * Snapshot of every watched file under the given directories.
     *
     * @param list<string> $directories
     *
     * @return array<string, int> absolute file path => mtime
     */
    public function snapshot(array $directories): array;
}
