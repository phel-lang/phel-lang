<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Formatter\Formatter;

final class FormatCommand
{
    public const COMMAND_NAME = 'fmt';

    private string $currentDir;
    private Formatter $formatter;

    public function __construct(
        string $currentDir,
        Formatter $formatter
    ) {
        $this->currentDir = $currentDir;
        $this->formatter = $formatter;
    }

    public function run(string $file): bool
    {
        $this->formatter->formatFile($file);

        return true;
    }
}
