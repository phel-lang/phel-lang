<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFacade;
use Phel\Command\Export\ExportCommand;
use Phel\Command\Format\FormatCommand;
use Phel\Command\Repl\ReplCommand;
use Phel\Command\Run\RunCommand;
use Phel\Command\Test\TestCommand;

/**
 * @method CommandFactory getFactory()
 */
final class CommandFacade extends AbstractFacade
{
    public function getReplCommand(): ReplCommand
    {
        return $this->getFactory()->createReplCommand();
    }

    public function getRunCommand(): RunCommand
    {
        return $this->getFactory()->createRunCommand();
    }

    public function getTestCommand(): TestCommand
    {
        return $this->getFactory()->createTestCommand();
    }

    public function getFormatCommand(): FormatCommand
    {
        return $this->getFactory()->createFormatCommand();
    }

    public function getExportCommand(): ExportCommand
    {
        return $this->getFactory()->createExportCommand();
    }
}
