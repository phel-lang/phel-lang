<?php

declare(strict_types=1);

namespace Phel\Profile;

use Gacela\Framework\AbstractFactory;
use Phel\Profile\Domain\Formatter\JsonFormatter;
use Phel\Profile\Domain\Formatter\TableFormatter;
use Phel\Profile\Domain\ProfilerSession;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * @extends AbstractFactory<ProfileConfig>
 */
final class ProfileFactory extends AbstractFactory
{
    public function createSession(): ProfilerSession
    {
        return new ProfilerSession();
    }

    public function createTableFormatter(): TableFormatter
    {
        return new TableFormatter();
    }

    public function createJsonFormatter(): JsonFormatter
    {
        return new JsonFormatter();
    }

    public function getRunFacade(): RunFacadeInterface
    {
        return $this->getProvidedDependency(ProfileProvider::FACADE_RUN);
    }
}
