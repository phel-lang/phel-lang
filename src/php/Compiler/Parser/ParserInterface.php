<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\ParserNode\FileNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Exceptions\Parser\UnexpectedParserException;
use Phel\Exceptions\Parser\UnfinishedParserException;
use Phel\Exceptions\ParserException;

interface ParserInterface
{
    /**
     * @throws ParserException
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseNext(TokenStream $tokenStream): ?NodeInterface;

    /**
     * @throws ParserException
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseAll(TokenStream $tokenStream): FileNode;
}
