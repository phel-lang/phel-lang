<?php

declare(strict_types=1);

namespace Phel\Shared\Api;

use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\ErrorCodeCatalog;

/**
 * `code` is what the tool reports under: a `PHELxxx` code from `phel analyze`,
 * a lint rule code from `phel lint`. `errorCode` is the `PHELxxx` code behind
 * it either way, so one error reads the same through both tools.
 *
 * Lines and columns are 1-based, `endCol` one past the last character.
 */
final readonly class Diagnostic
{
    public const string SEVERITY_ERROR = 'error';

    public const string SEVERITY_WARNING = 'warning';

    public const string SEVERITY_INFO = 'info';

    public const string SEVERITY_HINT = 'hint';

    /**
     * @param list<string> $suggestions
     */
    public function __construct(
        public string $code,
        public string $severity,
        public string $message,
        public string $uri,
        public int $startLine,
        public int $startCol,
        public int $endLine,
        public int $endCol,
        public ?string $errorCode = null,
        public array $suggestions = [],
        public ?string $fix = null,
    ) {}

    public static function fromLocatedException(
        AbstractLocatedException $e,
        ErrorCode $fallbackCode,
        string $uri,
    ): self {
        $errorCode = $e->getErrorCode() ?? $fallbackCode;

        return self::fromSourceSpan(
            code: $errorCode->value,
            severity: self::SEVERITY_ERROR,
            message: $e->getMessage(),
            uri: $uri,
            start: $e->getStartLocation(),
            end: $e->getEndLocation(),
            errorCode: $errorCode->value,
            suggestions: $e->getSuggestions(),
            fix: ErrorCodeCatalog::explain($errorCode)->fix,
        );
    }

    /**
     * The one place a 0-based {@see SourceLocation} column becomes a
     * diagnostic's 1-based column. A missing start reads as 1:1, a missing
     * end as the start.
     *
     * @param list<string> $suggestions
     */
    public static function fromSourceSpan(
        string $code,
        string $severity,
        string $message,
        string $uri,
        ?SourceLocation $start,
        ?SourceLocation $end,
        ?string $errorCode = null,
        array $suggestions = [],
        ?string $fix = null,
    ): self {
        $startLine = $start?->getLine() ?? 1;
        $startCol = $start instanceof SourceLocation ? $start->getColumn() + 1 : 1;

        return new self(
            code: $code,
            severity: $severity,
            message: $message,
            uri: $uri,
            startLine: $startLine,
            startCol: $startCol,
            endLine: $end?->getLine() ?? $startLine,
            endCol: $end instanceof SourceLocation ? $end->getColumn() + 1 : $startCol,
            errorCode: $errorCode,
            suggestions: $suggestions,
            fix: $fix,
        );
    }

    public function withCode(string $code): self
    {
        return $this->with(code: $code);
    }

    public function withSeverity(string $severity): self
    {
        return $this->with(severity: $severity);
    }

    public function withFix(?string $fix): self
    {
        return $this->with(fix: $fix);
    }

    /**
     * @return array{
     *     code: string,
     *     severity: string,
     *     message: string,
     *     uri: string,
     *     startLine: int,
     *     startCol: int,
     *     endLine: int,
     *     endCol: int,
     *     errorCode: string|null,
     *     suggestions: list<string>,
     *     fix: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
            'uri' => $this->uri,
            'startLine' => $this->startLine,
            'startCol' => $this->startCol,
            'endLine' => $this->endLine,
            'endCol' => $this->endCol,
            'errorCode' => $this->errorCode,
            'suggestions' => $this->suggestions,
            'fix' => $this->fix,
        ];
    }

    private function with(?string $code = null, ?string $severity = null, ?string $fix = null): self
    {
        return new self(
            code: $code ?? $this->code,
            severity: $severity ?? $this->severity,
            message: $this->message,
            uri: $this->uri,
            startLine: $this->startLine,
            startCol: $this->startCol,
            endLine: $this->endLine,
            endCol: $this->endCol,
            errorCode: $this->errorCode,
            suggestions: $this->suggestions,
            fix: $fix ?? $this->fix,
        );
    }
}
