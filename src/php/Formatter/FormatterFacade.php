<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\Framework\AbstractFacade;
use Phel\Formatter\Command\FormatCommand;

/**
 * @method FormatterFactory getFactory()
 */
final class FormatterFacade extends AbstractFacade implements FormatterFacadeInterface
{
    public function getFormatCommand(): FormatCommand
    {
        return $this->getFactory()->createFormatCommand();
    }
}
