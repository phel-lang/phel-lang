<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain;

use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;

interface FormatterInterface
{
    public const DEFAULT_SOURCE = 'string';

    /**
     * @throws AbstractParserException
     * @throws LexerValueException
     * @throws ZipperException
     *
     * @return string The formatted file result
     */
    public function format(string $string, string $source = self::DEFAULT_SOURCE): string;
}
