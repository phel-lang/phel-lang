<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Formatter;

use Phel\Lint\Domain\DiagnosticFormatterInterface;
use Phel\Lint\Transfer\LintResult;
use Phel\Shared\Api\Diagnostic;

use function getcwd;
use function realpath;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Emits the GitHub Actions workflow-command annotation format:
 *
 *     ::warning file=path,line=N,col=M,title=CODE::message
 *
 * One diagnostic per line. Values are sanitised per the GitHub spec:
 * `%`, `\r`, `\n` in messages are percent-encoded. `file` is relative to
 * the working directory, as GitHub resolves it against the checkout.
 *
 * @internal
 */
final readonly class GithubFormatter implements DiagnosticFormatterInterface
{
    public const string NAME = 'github';

    public function __construct(
        private ?string $workingDirectory = null,
    ) {}

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
            $this->encodeProperty($this->relativeToWorkingDirectory($diagnostic->uri)),
            $diagnostic->startLine,
            $diagnostic->startCol,
            $diagnostic->endLine,
            $diagnostic->endCol,
            $this->encodeProperty($diagnostic->code),
            $this->encodeData($diagnostic->message),
        );
    }

    private function relativeToWorkingDirectory(string $path): string
    {
        $cwd = $this->workingDirectory ?? getcwd();
        if ($cwd === false) {
            return $path;
        }

        // Lint reports real paths, and macOS spells `/tmp` as `/private/tmp`.
        foreach ([$cwd, realpath($cwd)] as $directory) {
            if ($directory === false || rtrim($directory, '/') === '') {
                continue;
            }

            $prefix = rtrim($directory, '/') . '/';
            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
        }

        return $path;
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
