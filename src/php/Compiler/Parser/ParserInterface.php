<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\Parser\ParserNode\NodeInterface;

interface ParserInterface
{
    public function parseNext(TokenStream $tokenStream): ?NodeInterface;
}
