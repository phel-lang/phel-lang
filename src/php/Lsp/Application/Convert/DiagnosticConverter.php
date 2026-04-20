<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Convert;

use Phel\Api\Transfer\Diagnostic;

/**
 * Convert a {@see Diagnostic} from the Api module into the shape expected by
 * LSP's `textDocument/publishDiagnostics` notification.
 */
final readonly class DiagnosticConverter
{
    public const int SEVERITY_ERROR = 1;

    public const int SEVERITY_WARNING = 2;

    public const int SEVERITY_INFO = 3;

    public const int SEVERITY_HINT = 4;

    public function __construct(
        private PositionConverter $positions,
        private UriConverter $uris,
    ) {}

    /**
     * @return array{
     *     range: array{start: array{line: int, character: int}, end: array{line: int, character: int}},
     *     severity: int,
     *     code: string,
     *     source: string,
     *     message: string,
     * }
     */
    public function toLspDiagnostic(Diagnostic $diagnostic): array
    {
        return [
            'range' => $this->positions->toLspRange(
                $diagnostic->startLine,
                $diagnostic->startCol,
                $diagnostic->endLine,
                $diagnostic->endCol,
            ),
            'severity' => $this->severityFromString($diagnostic->severity),
            'code' => $diagnostic->code,
            'source' => 'phel',
            'message' => $diagnostic->message,
        ];
    }

    /**
     * @param list<Diagnostic> $diagnostics
     *
     * @return array{
     *     uri: string,
     *     diagnostics: list<array{
     *         range: array{start: array{line: int, character: int}, end: array{line: int, character: int}},
     *         severity: int,
     *         code: string,
     *         source: string,
     *         message: string,
     *     }>,
     * }
     */
    public function toPublishParams(string $uri, array $diagnostics): array
    {
        $items = [];
        foreach ($diagnostics as $diagnostic) {
            $items[] = $this->toLspDiagnostic($diagnostic);
        }

        $clientUri = $this->uris->isFileUri($uri) ? $uri : $this->uris->fromFilePath($uri);

        return [
            'uri' => $clientUri,
            'diagnostics' => $items,
        ];
    }

    private function severityFromString(string $severity): int
    {
        return match ($severity) {
            Diagnostic::SEVERITY_ERROR => self::SEVERITY_ERROR,
            Diagnostic::SEVERITY_WARNING => self::SEVERITY_WARNING,
            Diagnostic::SEVERITY_INFO => self::SEVERITY_INFO,
            Diagnostic::SEVERITY_HINT => self::SEVERITY_HINT,
            default => self::SEVERITY_INFO,
        };
    }
}
