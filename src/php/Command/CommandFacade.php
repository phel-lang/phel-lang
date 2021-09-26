<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFacade;
use Phel\Command\Format\FormatCommand;
use Phel\Command\Repl\ReplCommand;
use Phel\Command\Run\RunCommand;
use Phel\Command\Test\TestCommand;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @method CommandFactory getFactory()
 */
final class CommandFacade extends AbstractFacade implements CommandFacadeInterface
{
    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $locatedException,
        CodeSnippet $snippet
    ): void {
        $this->getFactory()
            ->createCommandExceptionWriter()
            ->writeLocatedException($output, $locatedException, $snippet);
    }

    public function writeStackTrace(OutputInterface $output, Throwable $e): void
    {
        $this->getFactory()
            ->createCommandExceptionWriter()
            ->writeStackTrace($output, $e);
    }

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
}
