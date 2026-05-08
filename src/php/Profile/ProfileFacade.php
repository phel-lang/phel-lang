<?php

declare(strict_types=1);

namespace Phel\Profile;

use Gacela\Framework\AbstractFacade;
use Phel\Profile\Domain\ProfileReport;
use Phel\Profile\Domain\ProfilerSession;
use Phel\Profile\Domain\SortOrder;

/**
 * @extends AbstractFacade<ProfileFactory>
 */
final class ProfileFacade extends AbstractFacade
{
    public function startSession(): ProfilerSession
    {
        return $this->getFactory()->createSession();
    }

    public function renderTable(ProfileReport $report, int $top, SortOrder $sort, bool $includeCompilePhases): string
    {
        return $this->getFactory()->createTableFormatter()->render($report, $top, $sort, $includeCompilePhases);
    }

    public function renderJson(ProfileReport $report): string
    {
        return $this->getFactory()->createJsonFormatter()->render($report);
    }
}
