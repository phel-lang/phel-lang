<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser;

use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Shared\Parser\Node\FileNode;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * @internal
 */
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

    /**
     * Parses exactly one expression off the stream.
     *
     * The sub-parsers in `ExpressionParser/` recurse through this method, which
     * is why it belongs on the contract: it lets them depend on this Domain
     * interface instead of the concrete `Application\Parser` that owns them.
     *
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function readExpression(TokenStream $tokenStream): NodeInterface;
}
