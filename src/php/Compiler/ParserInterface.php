<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Parser\ParserNode\NodeInterface;

interface ParserInterface
{
    public function parseNext(TokenStream $tokenStream): ?NodeInterface;
}
