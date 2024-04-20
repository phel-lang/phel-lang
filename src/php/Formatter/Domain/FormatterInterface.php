<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain;

use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Transpiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Transpiler\Domain\Parser\Exceptions\AbstractParserException;

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
