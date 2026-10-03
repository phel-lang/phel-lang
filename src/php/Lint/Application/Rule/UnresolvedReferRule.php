<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\LintRuleCodes;

/**
 * Promotes the analyzer's `PHEL013`: a `:refer` of a name the required
 * namespace does not define, or keeps private. The analyzer can only tell
 * once that namespace is loaded, which the analysis stage does for the
 * file's dependencies before it reads the `ns` form.
 *
 * @internal
 */
final readonly class UnresolvedReferRule implements LintRuleInterface
{
    public function code(): string
    {
        return LintRuleCodes::UNRESOLVED_REFER;
    }

    public function apply(FileAnalysis $analysis): array
    {
        return SemanticDiagnosticPromoter::promote(
            $analysis,
            ErrorCode::UNRESOLVED_REFER->value,
            $this->code(),
        );
    }
}
