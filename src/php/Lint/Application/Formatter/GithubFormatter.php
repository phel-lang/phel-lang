<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Formatter;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Domain\DiagnosticFormatterInterface;
use Phel\Lint\Transfer\LintResult;

use function sprintf;
use function str_replace;

/**
 * Emits the GitHub Actions workflow-command annotation format:
 *
 *     ::warning file=path,line=N,col=M,title=CODE::message
 *
 * One diagnostic per line. Values are sanitised per the GitHub spec:
 * `%`, `\r`, `\n` in messages are percent-encoded.
 */
final class GithubFormatter implements DiagnosticFormatterInterface
{
    public const string NAME = 'github';

    public function name(): string
    {
        return self::NAME;
    }

    public function format(LintResult $result): string
    {
        $lines = [];
        foreach ($result->diagnostics as $diagnostic) {
            $lines[] = $this->formatDiagnostic($diagnostic);
        }

        return implode("\n", $lines);
    }

    private function formatDiagnostic(Diagnostic $diagnostic): string
    {
        $level = match ($diagnostic->severity) {
            Diagnostic::SEVERITY_ERROR => 'error',
            Diagnostic::SEVERITY_INFO, Diagnostic::SEVERITY_HINT => 'notice',
            default => 'warning',
        };

        return sprintf(
            '::%s file=%s,line=%d,col=%d,endLine=%d,endColumn=%d,title=%s::%s',
            $level,
            $this->encodeProperty($diagnostic->uri),
            $diagnostic->startLine,
            $diagnostic->startCol,
            $diagnostic->endLine,
            $diagnostic->endCol,
            $this->encodeProperty($diagnostic->code),
            $this->encodeData($diagnostic->message),
        );
    }

    /**
     * GitHub property values escape `%`, `\r`, `\n`, `:`, `,`.
     */
    private function encodeProperty(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n", ':', ','],
            ['%25', '%0D', '%0A', '%3A', '%2C'],
            $value,
        );
    }

    /**
     * Message data escapes `%`, `\r`, `\n` only.
     */
    private function encodeData(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n"],
            ['%25', '%0D', '%0A'],
            $value,
        );
    }
}
