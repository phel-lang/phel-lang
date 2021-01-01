<?php

declare(strict_types=1);

namespace Phel\Compiler;

interface LexerInterface
{
    public const DEFAULT_SOURCE = 'string';

    public function lexString(string $code, string $source = self::DEFAULT_SOURCE): TokenStream;
}
