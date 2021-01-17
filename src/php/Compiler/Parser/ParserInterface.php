<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\ParserNode\FileNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;

interface ParserInterface
{
    public function parseNext(TokenStream $tokenStream): ?NodeInterface;

    public function parseAll(TokenStream $tokenStream): FileNode;
}
