<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\OpenForm;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\ReaderCondSplicingNode;

/**
 * @internal
 */
final readonly class ListParser
{
    public function __construct(private ParserInterface $parser) {}

    /**
     * @throws UnfinishedParserException
     */
    public function parse(TokenStream $tokenStream, int $endTokenType, int $tokenType): ListNode
    {
        $acc = [];
        $startToken = $tokenStream->current();
        $startLocation = $startToken->getStartLocation();
        $tokenStream->next();

        while ($tokenStream->valid()) {
            $token = $tokenStream->current();

            if ($token->getType() === $endTokenType) {
                $endLocation = $token->getEndLocation();
                $tokenStream->next();

                return new ListNode($tokenType, $startLocation, $endLocation, $acc);
            }

            $node = $this->parser->readExpression($tokenStream);
            if ($node instanceof ReaderCondSplicingNode) {
                array_push($acc, ...$node->getChildren());
            } else {
                $acc[] = $node;
            }
        }

        // The loop only ends here once the stream is exhausted, so there is no
        // `current()` token left: asking for one throws "Token generator
        // exhausted unexpectedly" and destroys the message being built, which
        // is the one the user needs. Anchor on the opening bracket instead.
        $openForm = new OpenForm($startToken);

        throw UnfinishedParserException::forSnippet(
            $tokenStream->getCodeSnippet(),
            $startToken,
            $openForm->unterminatedMessage(),
            $openForm->getErrorCode(),
        );
    }
}
