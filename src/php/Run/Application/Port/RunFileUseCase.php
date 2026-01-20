<?php

declare(strict_types=1);

namespace Phel\Run\Application\Port;

/**
 * Driving port (primary port) for running a Phel file.
 */
interface RunFileUseCase
{
    /**
     * Runs a Phel file by path.
     */
    public function execute(string $filename): void;
}
