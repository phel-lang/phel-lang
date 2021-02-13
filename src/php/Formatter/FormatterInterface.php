<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Phel\Compiler\Parser\Exceptions\AbstractParserException;

interface FormatterInterface
{
    public const DEFAULT_SOURCE = 'string';

    /**
     * @throws AbstractParserException
     *
     * @return string The formatted file result
     */
    public function format(string $string, string $source = self::DEFAULT_SOURCE): string;
}
