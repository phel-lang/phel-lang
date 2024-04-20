<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Lexer;

use Phel\Transpiler\Domain\Lexer\Exceptions\LexerValueException;

interface LexerInterface
{
    public const DEFAULT_SOURCE = 'string';

    /**
     * @throws LexerValueException
     */
    public function lexString(string $code, string $source = self::DEFAULT_SOURCE, int $startingLine = 1): TokenStream;
}
