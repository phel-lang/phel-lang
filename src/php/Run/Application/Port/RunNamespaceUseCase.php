<?php

declare(strict_types=1);

namespace Phel\Run\Application\Port;

/**
 * Driving port (primary port) for running a Phel namespace.
 */
interface RunNamespaceUseCase
{
    /**
     * Runs a Phel namespace by name.
     */
    public function execute(string $namespace): void;
}
