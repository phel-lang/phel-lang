<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\ReaderCondSplicingNode;
use Phel\Shared\Parser\Node\Token;

use function sprintf;

/**
 * @internal
 */
final readonly class ListParser
{
    private const array CLOSING_BRACKETS = [
        Token::T_CLOSE_PARENTHESIS => ')',
        Token::T_CLOSE_BRACKET => ']',
        Token::T_CLOSE_BRACE => '}',
    ];

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

        $closingBracket = self::CLOSING_BRACKETS[$endTokenType] ?? ')';
        $message = sprintf(
            "Unterminated list starting at line %d. Did you forget a closing '%s'?",
            $startLocation->getLine(),
            $closingBracket,
        );

        // The loop only ends here once the stream is exhausted, so there is no
        // `current()` token left: asking for one throws "Token generator
        // exhausted unexpectedly" and destroys the message above, which is the
        // one the user needs. Anchor on the opening bracket instead.
        throw UnfinishedParserException::forExhaustedStream(
            $tokenStream->getCodeSnippet(),
            $startLocation,
            $message,
            ErrorCode::UNTERMINATED_LIST,
        );
    }
}
