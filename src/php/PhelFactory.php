<?php

declare(strict_types=1);

namespace Phel;

use Gacela\Framework\AbstractFactory;
use Phel\Command\CommandFacadeInterface;

final class PhelFactory extends AbstractFactory
{
    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(PhelDependencyProvider::FACADE_COMMAND);
    }
}
