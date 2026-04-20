<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lang\SourceLocation;
use Phel\Lang\TypeInterface;

/**
 * Small helper so each rule can produce a Diagnostic without repeating
 * location-extraction logic. Severity is a placeholder at rule time
 * (`Diagnostic::SEVERITY_WARNING`); the facade rewrites it based on
 * the configured severity for the rule code.
 */
final class DiagnosticBuilder
{
    public static function fromForm(
        string $code,
        string $message,
        string $uri,
        TypeInterface|string|float|int|bool|null $form,
    ): Diagnostic {
        [$startLine, $startCol, $endLine, $endCol] = self::locationOf($form);

        return new Diagnostic(
            code: $code,
            severity: Diagnostic::SEVERITY_WARNING,
            message: $message,
            uri: $uri,
            startLine: $startLine,
            startCol: $startCol,
            endLine: $endLine,
            endCol: $endCol,
        );
    }

    /**
     * @return array{int, int, int, int}
     *
     * @param TypeInterface|null|scalar $form
     */
    private static function locationOf(mixed $form): array
    {
        if ($form instanceof TypeInterface) {
            $start = $form->getStartLocation();
            $end = $form->getEndLocation();

            $startLine = $start instanceof SourceLocation ? $start->getLine() : 1;
            $startCol = $start instanceof SourceLocation ? $start->getColumn() : 1;
            $endLine = $end instanceof SourceLocation ? $end->getLine() : $startLine;
            $endCol = $end instanceof SourceLocation ? $end->getColumn() : $startCol;

            return [$startLine, $startCol, $endLine, $endCol];
        }

        return [1, 1, 1, 1];
    }
}
