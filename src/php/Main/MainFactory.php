<?php

declare(strict_types=1);

namespace Phel\Main;

use Gacela\Framework\AbstractFactory;
use Phel\Command\CommandFacadeInterface;

final class MainFactory extends AbstractFactory
{
    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(MainDependencyProvider::FACADE_COMMAND);
    }
}
