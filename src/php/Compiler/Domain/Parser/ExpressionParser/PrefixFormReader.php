<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function in_array;

/**
 * @internal
 */
final class PrefixFormReader
{
    /**
     * @return array{NodeInterface, list<NodeInterface>, string}
     */
    public static function read(ParserInterface $parser, TokenStream $stream, Token $prefix): array
    {
        $trivia = [];
        $code = '';
        while (true) {
            if (!$stream->valid() || in_array($stream->current()->getType(), [Token::T_EOF, Token::T_CLOSE_PARENTHESIS, Token::T_CLOSE_BRACKET, Token::T_CLOSE_BRACE], true)) {
                throw UnexpectedParserException::forSnippet(
                    $stream->getCodeSnippet(),
                    $prefix,
                    'Expected a form after reader prefix ' . $prefix->getCode(),
                );
            }

            $form = $parser->readExpression($stream);
            if (!$form instanceof TriviaNodeInterface) {
                return [$form, $trivia, $code];
            }

            $trivia[] = $form;
            $code .= $form->getCode();
        }
    }
}
