<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Parser;

use Phel\Transpiler\Domain\Lexer\TokenStream;
use Phel\Transpiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Transpiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Transpiler\Domain\Parser\ParserNode\FileNode;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;

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
