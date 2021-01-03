<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Parser\Parser\ParserNode\NodeInterface;
use Phel\Compiler\TokenStream;

interface ParserInterface
{
    public function parseNext(TokenStream $tokenStream): ?NodeInterface;
}
