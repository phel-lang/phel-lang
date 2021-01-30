<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Phel\Exceptions\ParserException;

interface FormatterInterface
{
    /**
     * @throws ParserException
     *
     * @return bool True if the file was formatted. False if the file wasn't altered because it was already formatted.
     */
    public function formatFile(string $filename): bool;
}
