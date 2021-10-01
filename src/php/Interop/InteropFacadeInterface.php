<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Command\ExportCommand;

interface InteropFacadeInterface
{
    public function getExportCommand(): ExportCommand;
}
