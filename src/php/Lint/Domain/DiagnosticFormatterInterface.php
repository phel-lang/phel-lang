<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

use Phel\Lint\Transfer\LintResult;

interface DiagnosticFormatterInterface
{
    /**
     * Format key used to select this formatter on the CLI.
     */
    public function name(): string;

    public function format(LintResult $result): string;
}
