<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Parser\ParserNode\FileNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;

interface ParserInterface
{
    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseNext(TokenStream $tokenStream): ?NodeInterface;

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseAll(TokenStream $tokenStream): FileNode;
}
