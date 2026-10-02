<?php

declare(strict_types=1);

namespace Phel\Shared\Api;

use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\ErrorCodeCatalog;

/**
 * `code` is what the tool reports under: a `PHELxxx` code from `phel analyze`,
 * a lint rule code from `phel lint`. `errorCode` is the `PHELxxx` code behind
 * it either way, so one error reads the same through both tools.
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
        $start = $e->getStartLocation();
        $end = $e->getEndLocation();
        $errorCode = $e->getErrorCode() ?? $fallbackCode;

        return new self(
            code: $errorCode->value,
            severity: self::SEVERITY_ERROR,
            message: $e->getMessage(),
            uri: $uri,
            startLine: $start?->getLine() ?? 1,
            startCol: $start?->getColumn() ?? 1,
            endLine: $end?->getLine() ?? ($start?->getLine() ?? 1),
            endCol: $end?->getColumn() ?? ($start?->getColumn() ?? 1),
            errorCode: $errorCode->value,
            suggestions: $e->getSuggestions(),
            fix: ErrorCodeCatalog::explain($errorCode)->fix,
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
