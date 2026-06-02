<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser;

use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Shared\Parser\Node\FileNode;
use Phel\Shared\Parser\Node\NodeInterface;

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
