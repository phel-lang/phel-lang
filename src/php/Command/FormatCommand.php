<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Formatter\FormatterInterface;

final class FormatCommand
{
    public const COMMAND_NAME = 'fmt';

    private FormatterInterface $formatter;

    public function __construct(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    public function run(string $file): bool
    {
        $this->formatter->formatFile($file);

        return true;
    }
}
