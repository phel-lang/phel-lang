<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

use Phel\Shared\Exceptions\ErrorCode;

/**
 * A rule that reports an analyzer code under its own rule code. The
 * `phel/compile-error` catch-all leaves that code to it.
 *
 * @internal
 */
interface PromotingRuleInterface extends LintRuleInterface
{
    public function promotedErrorCode(): ErrorCode;
}
