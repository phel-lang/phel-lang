<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\TypeInterface;
use Phel\Shared\Api\Diagnostic;

/**
 * Small helper so each rule can produce a Diagnostic without repeating
 * location-extraction logic. Severity is a placeholder at rule time
 * (`Diagnostic::SEVERITY_WARNING`); the facade rewrites it based on
 * the configured severity for the rule code.
 *
 * @internal
 */
final class DiagnosticBuilder
{
    public static function fromForm(
        string $code,
        string $message,
        string $uri,
        TypeInterface|string|float|int|bool|null $form,
    ): Diagnostic {
        return Diagnostic::fromSourceSpan(
            code: $code,
            severity: Diagnostic::SEVERITY_WARNING,
            message: $message,
            uri: $uri,
            start: $form instanceof TypeInterface ? $form->getStartLocation() : null,
            end: $form instanceof TypeInterface ? $form->getEndLocation() : null,
        );
    }
}
