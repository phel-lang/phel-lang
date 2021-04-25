<?php

declare(strict_types=1);

namespace Phel;

use Gacela\Framework\AbstractFacade;
use InvalidArgumentException;
use Phel\Command\Export\ExportCommand;
use Phel\Command\Format\FormatCommand;
use Phel\Command\Repl\ReplCommand;
use Phel\Command\Run\RunCommand;
use Phel\Command\Test\TestCommand;

/**
 * @method PhelFactory getFactory()
 */
final class PhelFacade extends AbstractFacade
{
    public const HELP_TEXT = <<<HELP
Usage: phel [command]

Commands:
    repl
        Start a Repl.

    run <filename-or-namespace>
        Runs a script.

    test <filename> <filename> ...
        Tests the given files. If no filenames are provided all tests in the
        "tests" directory are executed.

    format <filename-or-directory> ...
        Formats the given files.

    export
        Export all definitions with the meta data `@{:export true}` as PHP classes.
        By default, it will search in the `src/` directory. All configuration
        options need to be set in composer.json.

    help
        Show this help message.

HELP;

    /**
     * @throws InvalidArgumentException
     */
    public function runCommand(string $commandName, array $arguments = []): void
    {
        switch ($commandName) {
            case ReplCommand::COMMAND_NAME:
                $this->executeReplCommand();
                break;
            case RunCommand::COMMAND_NAME:
                $this->executeRunCommand($arguments);
                break;
            case TestCommand::COMMAND_NAME:
                $this->executeTestCommand($arguments);
                break;
            case FormatCommand::COMMAND_NAME:
                $this->executeFormatCommand($arguments);
                break;
           case ExportCommand::COMMAND_NAME:
                $this->executeExportCommand();
                break;
            default:
                throw new InvalidArgumentException(self::HELP_TEXT);
        }
    }

    private function executeReplCommand(): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->executeReplCommand();
    }

    private function executeRunCommand(array $arguments): void
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('Please, provide a filename or namespace as argument!');
        }

        $this->getFactory()
            ->getCommandFacade()
            ->executeRunCommand($arguments[0]);
    }

    private function executeTestCommand(array $arguments): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->executeTestCommand($arguments);
    }

    private function executeFormatCommand(array $arguments): void
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('Please, provide a filename or a directory as arguments!');
        }

        $this->getFactory()
            ->getCommandFacade()
            ->executeFormatCommand($arguments);
    }

    private function executeExportCommand(): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->executeExportCommand();
    }
}
