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
    /**
     * Start a new profiling session.
     *
     * Install the returned session as `Registry::$profilerHook` for the run,
     * then call `stop()` to collect the {@see ProfileReport}.
     */
    public function startSession(): ProfilerSession
    {
        return $this->getFactory()->createSession();
    }

    /**
     * Render the report as a formatted ASCII table.
     *
     * @param int  $top                  Maximum number of fns shown; results are truncated
     * @param bool $includeCompilePhases When true, prepend the per-source compile-phase breakdown
     */
    public function renderTable(ProfileReport $report, int $top, SortOrder $sort, bool $includeCompilePhases): string
    {
        return $this->getFactory()->createTableFormatter()->render($report, $top, $sort, $includeCompilePhases);
    }

    /**
     * Serialize the report to a JSON string.
     */
    public function renderJson(ProfileReport $report): string
    {
        return $this->getFactory()->createJsonFormatter()->render($report);
    }
}
