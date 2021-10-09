<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFacade;
use Phel\Run\Command\ReplCommand;
use Phel\Run\Command\RunCommand;
use Phel\Run\Command\TestCommand;

/**
 * @method RunFactory getFactory()
 */
final class RunFacade extends AbstractFacade implements RunFacadeInterface
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

    public function runNamespace(string $namespace): void
    {
        $this->getFactory()
            ->createNamespaceRunner()
            ->run($namespace);
    }
}
