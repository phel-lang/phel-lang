<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain;

/**
 * @internal
 */
enum MutantVerdict: string
{
    /**
     * The tests noticed: at least one failed or errored.
     */
    case Killed = 'killed';

    /**
     * Every test still passed; the mutant escaped.
     */
    case Survived = 'survived';

    /**
     * The mutant did not compile; not a test gap.
     */
    case Error = 'error';

    /**
     * The tests did not finish within the budget; treated as noticed.
     */
    case Timeout = 'timeout';

    /**
     * No test executes the definition; nothing was run.
     */
    case NotCovered = 'not-covered';
}
