<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer;

use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Shared\CompilerConstants;

/**
 * @internal
 */
interface LexerInterface
{
    public const string DEFAULT_SOURCE = CompilerConstants::DEFAULT_SOURCE;

    /**
     * @throws LexerValueException
     */
    public function lexString(string $code, string $source = self::DEFAULT_SOURCE, int $startingLine = 1): TokenStream;
}
