<?php

declare(strict_types=1);

namespace Phel\Api\Application\Port;

use Phel\Api\Domain\Transfer\PhelFunction;

/**
 * Driving port (primary port) for retrieving Phel function information.
 */
interface GetPhelFunctionsUseCase
{
    /**
     * Gets all Phel functions from the loaded namespaces.
     *
     * @return list<PhelFunction>
     */
    public function execute(): array;
}
