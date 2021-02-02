<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Phel\Compiler\Parser\Exceptions\AbstractParserException;

interface FormatterInterface
{
    /**
     * @throws AbstractParserException
     *
     * @return bool True if the file was formatted. False if the file wasn't altered because it was already formatted.
     */
    public function formatFile(string $filename): bool;
}
