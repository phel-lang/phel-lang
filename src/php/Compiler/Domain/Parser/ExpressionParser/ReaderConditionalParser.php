<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Shared\Parser\Node\CommentNode;
use Phel\Shared\Parser\Node\KeywordNode;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\ReaderCondSplicingNode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

/**
 * Parses reader conditionals `#?(:phel ... :default ...)` and their splicing
 * variant `#?@(:phel [...] :default [...])`.
 *
 * Both forms scan the same `keyword form` branch pairs and select the
 * `:phel` branch (falling back to `:default`); they differ only in how the
 * matched branch is wrapped. The shared scan + closing-paren handling lives
 * in {@see resolveBranch()}.
 */
final readonly class ReaderConditionalParser
{
    public function __construct(private Parser $parser) {}

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseCond(TokenStream $tokenStream, Token $openToken): NodeInterface
    {
        $matchedNode = $this->resolveBranch($tokenStream, $openToken, 'Unterminated reader conditional #?()');

        if ($matchedNode instanceof NodeInterface) {
            return $matchedNode;
        }

        // No matching branch → treated as trivia (dropped)
        return CommentNode::createWithToken($openToken);
    }

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseCondSplicing(TokenStream $tokenStream, Token $openToken): NodeInterface
    {
        $matchedNode = $this->resolveBranch($tokenStream, $openToken, 'Unterminated reader conditional splicing #?@()');

        if ($matchedNode instanceof ListNode) {
            return new ReaderCondSplicingNode(
                $matchedNode->getChildren(),
                $openToken->getStartLocation(),
                $matchedNode->getEndLocation(),
            );
        }

        if ($matchedNode instanceof NodeInterface) {
            throw UnexpectedParserException::forSnippet(
                $tokenStream->getCodeSnippet(),
                $openToken,
                'Reader conditional splicing #?@() requires a sequential collection (list or vector), got: ' . $matchedNode->getCode(),
            );
        }

        // No matching branch → splice nothing
        return new ReaderCondSplicingNode(
            [],
            $openToken->getStartLocation(),
            $openToken->getEndLocation(),
        );
    }

    /**
     * Scans the `:phel`/`:default` branch pairs, consumes the closing paren,
     * and returns the selected branch (`:phel`, else `:default`, else null).
     *
     * @throws UnfinishedParserException
     */
    private function resolveBranch(TokenStream $tokenStream, Token $openToken, string $unterminatedMessage): ?NodeInterface
    {
        $phelNode = null;
        $defaultNode = null;

        while ($tokenStream->valid()) {
            $keywordNode = $this->readNonTriviaExpression($tokenStream);
            if (!$keywordNode instanceof NodeInterface) {
                break;
            }

            $formNode = $this->readNonTriviaExpression($tokenStream);
            if (!$formNode instanceof NodeInterface) {
                break;
            }

            if ($keywordNode instanceof KeywordNode) {
                $keywordCode = $keywordNode->getCode();
                if ($keywordCode === ':phel') {
                    $phelNode = $formNode;
                } elseif ($keywordCode === ':default') {
                    $defaultNode = $formNode;
                }
            }
        }

        // Consume the closing paren — throw if the stream is exhausted or missing ')'
        if (!$tokenStream->valid() || $tokenStream->current()->getType() !== Token::T_CLOSE_PARENTHESIS) {
            throw UnfinishedParserException::forSnippet($tokenStream->getCodeSnippet(), $openToken, $unterminatedMessage);
        }

        $tokenStream->next();

        return $phelNode ?? $defaultNode;
    }

    /**
     * Reads the next non-trivia expression inside a reader conditional.
     * Returns null when only trivia remains before the closing paren (or EOF),
     * letting the caller exit the branch loop cleanly.
     *
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    private function readNonTriviaExpression(TokenStream $tokenStream): ?NodeInterface
    {
        while ($tokenStream->valid()) {
            if ($tokenStream->current()->getType() === Token::T_CLOSE_PARENTHESIS) {
                return null;
            }

            $node = $this->parser->readExpression($tokenStream);
            if (!$node instanceof TriviaNodeInterface) {
                return $node;
            }
        }

        return null;
    }
}
