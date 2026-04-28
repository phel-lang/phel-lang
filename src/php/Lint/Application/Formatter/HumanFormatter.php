<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Formatter;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Domain\DiagnosticFormatterInterface;
use Phel\Lint\Transfer\LintResult;

use function sprintf;

/**
 * Human-readable single-line-per-diagnostic format:
 *
 *     file:line:col severity code message
 */
final class HumanFormatter implements DiagnosticFormatterInterface
{
    public const string NAME = 'human';

    public function name(): string
    {
        return self::NAME;
    }

    public function format(LintResult $result): string
    {
        if ($result->diagnostics === []) {
            return 'No lint issues found.';
        }

        $lines = [];
        foreach ($result->diagnostics as $diagnostic) {
            $lines[] = $this->formatDiagnostic($diagnostic);
        }

        $lines[] = '';
        $lines[] = sprintf(
            '%d issue(s): %d error(s), %d warning(s), %d info.',
            $result->totalCount(),
            $result->errorCount(),
            $result->warningCount(),
            $result->infoCount(),
        );

        return implode("\n", $lines);
    }

    private function formatDiagnostic(Diagnostic $diagnostic): string
    {
        return sprintf(
            '%s:%d:%d %s %s %s',
            $diagnostic->uri,
            $diagnostic->startLine,
            $diagnostic->startCol,
            $this->severityLabel($diagnostic->severity),
            $diagnostic->code,
            $diagnostic->message,
        );
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            Diagnostic::SEVERITY_ERROR => '[error]',
            Diagnostic::SEVERITY_WARNING => '[warning]',
            Diagnostic::SEVERITY_INFO => '[info]',
            Diagnostic::SEVERITY_HINT => '[hint]',
            default => '[' . $severity . ']',
        };
    }
}
