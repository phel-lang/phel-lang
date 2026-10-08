<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Lint\Domain\PromotingRuleInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\LintRuleCodes;

use function in_array;

/**
 * Reports every analyzer error no dedicated rule promotes, so a file
 * `phel run` rejects never lints clean. The `PHELxxx` code stays in
 * `errorCode`.
 *
 * @internal
 */
final readonly class CompileErrorRule implements LintRuleInterface
{
    /**
     * @param list<string> $promotedCodes
     */
    private function __construct(
        private array $promotedCodes,
    ) {}

    /**
     * @param list<LintRuleInterface> $rules
     */
    public static function besides(array $rules): self
    {
        $promotedCodes = [];
        foreach ($rules as $rule) {
            if ($rule instanceof PromotingRuleInterface) {
                $promotedCodes[] = $rule->promotedErrorCode()->value;
            }
        }

        return new self($promotedCodes);
    }

    public function code(): string
    {
        return LintRuleCodes::COMPILE_ERROR;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $result = [];
        foreach ($analysis->semanticDiagnostics as $diagnostic) {
            if ($diagnostic->severity !== Diagnostic::SEVERITY_ERROR
                || in_array($diagnostic->code, $this->promotedCodes, true)
            ) {
                continue;
            }

            $result[] = $diagnostic->withCode($this->code());
        }

        return $result;
    }
}
