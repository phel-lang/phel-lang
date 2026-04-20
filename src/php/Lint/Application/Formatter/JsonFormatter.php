<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Formatter;

use Phel\Lint\Domain\DiagnosticFormatterInterface;
use Phel\Lint\Transfer\LintResult;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Stable JSON array of diagnostic objects.
 * Keys match `Diagnostic::toArray()` for editor/CI consumption.
 */
final class JsonFormatter implements DiagnosticFormatterInterface
{
    public const string NAME = 'json';

    public function name(): string
    {
        return self::NAME;
    }

    public function format(LintResult $result): string
    {
        return json_encode(
            $result->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }
}
