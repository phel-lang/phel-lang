<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Phel\Formatter\Command\FormatCommand;

interface FormatterFacadeInterface
{
    public function getFormatCommand(): FormatCommand;
}
