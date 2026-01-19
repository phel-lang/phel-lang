<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Exceptions\ErrorCode;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;

use function sprintf;

final readonly class ListParser
{
    private const array CLOSING_BRACKETS = [
        Token::T_CLOSE_PARENTHESIS => ')',
        Token::T_CLOSE_BRACKET => ']',
        Token::T_CLOSE_BRACE => '}',
    ];

    public function __construct(private Parser $parser)
    {
    }

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

            $acc[] = $this->parser->readExpression($tokenStream);
        }

        $closingBracket = self::CLOSING_BRACKETS[$endTokenType] ?? ')';
        $message = sprintf(
            "Unterminated list starting at line %d. Did you forget a closing '%s'?",
            $startLocation->getLine(),
            $closingBracket,
        );

        throw UnfinishedParserException::forSnippet(
            $tokenStream->getCodeSnippet(),
            $tokenStream->current(),
            $message,
            ErrorCode::UNTERMINATED_LIST,
        );
    }
}
