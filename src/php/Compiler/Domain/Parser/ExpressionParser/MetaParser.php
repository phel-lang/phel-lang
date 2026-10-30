<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Shared\Parser\Node\MetaNode;

/**
 * @internal
 */
final readonly class MetaParser
{
    public function __construct(private ParserInterface $parser) {}

    public function parse(TokenStream $tokenStream): MetaNode
    {
        $token = $tokenStream->current();
        $startLocation = $token->getStartLocation();
        $tokenStream->next();

        [$meta, , $prefixTrivia] = PrefixFormReader::read($this->parser, $tokenStream, $token);
        [$object, $children] = PrefixFormReader::read($this->parser, $tokenStream, $token);
        $children[] = $object;

        $endLocation = $tokenStream->current()->getEndLocation();

        return new MetaNode($meta, $startLocation, $endLocation, $children, $prefixTrivia);
    }
}
