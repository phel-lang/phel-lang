<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

/**
 * @internal
 */
enum BalanceOutcome: string
{
    case Balanced = 'balanced';

    /**
     * Delimiters were missing and `--fix` wrote them.
     */
    case Repaired = 'repaired';

    /**
     * Delimiters are missing and could be appended, but `--fix` was not given.
     */
    case NeedsRepair = 'needs-repair';

    /**
     * Broken in a way no automatic repair can resolve without guessing.
     */
    case Unrepairable = 'unrepairable';
}
