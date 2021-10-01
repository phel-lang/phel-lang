<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractFacade;
use Phel\Interop\Command\ExportCommand;

/**
 * @method InteropFactory getFactory()
 */
final class InteropFacade extends AbstractFacade implements InteropFacadeInterface
{
    public function getExportCommand(): ExportCommand
    {
        return $this->getFactory()->createExportCommand();
    }
}
