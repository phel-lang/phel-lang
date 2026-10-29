<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\CommentNode;
use Phel\Shared\Parser\Node\KeywordNode;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\ReaderConditionalNode;
use Phel\Shared\Parser\Node\ReaderCondSplicingNode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

/**
 * @internal
 */
final readonly class ReaderConditionalParser
{
    public function __construct(private ParserInterface $parser) {}

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseCond(TokenStream $tokenStream, Token $openToken): NodeInterface
    {
        [$matchedNode, $end, $code, $prefix, $suffix] = $this->resolveBranch($tokenStream, $openToken, 'Unterminated reader conditional #?()');

        if ($matchedNode instanceof NodeInterface) {
            return new ReaderConditionalNode($code, $matchedNode, $openToken->getStartLocation(), $end, $prefix, $suffix);
        }

        return new CommentNode($code, $openToken->getStartLocation(), $end);
    }

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseCondSplicing(TokenStream $tokenStream, Token $openToken): NodeInterface
    {
        [$matchedNode, $end, $code, $prefix, $suffix] = $this->resolveBranch($tokenStream, $openToken, 'Unterminated reader conditional splicing #?@()');

        $source = $matchedNode === null ? null : new ReaderConditionalNode($code, $matchedNode, $openToken->getStartLocation(), $end, $prefix, $suffix);

        while ($matchedNode instanceof ReaderConditionalNode) {
            $matchedNode = $matchedNode->getSelected();
        }

        if ($matchedNode instanceof ListNode) {
            return new ReaderCondSplicingNode(
                $matchedNode->getChildren(),
                $openToken->getStartLocation(),
                $end,
                $code,
                $source,
            );
        }

        if ($matchedNode instanceof NodeInterface) {
            throw UnexpectedParserException::forSnippet(
                $tokenStream->getCodeSnippet(),
                $openToken,
                'Reader conditional splicing #?@() requires a sequential collection (list or vector), got: ' . $matchedNode->getCode(),
            );
        }

        return new ReaderCondSplicingNode(
            [],
            $openToken->getStartLocation(),
            $end,
            $code,
        );
    }

    /**
     * Scans the `:phel`/`:default` branch pairs, consumes the closing paren,
     * and returns the selected branch (`:phel`, else `:default`, else null).
     *
     * @throws UnfinishedParserException
     *
     * @return array{?NodeInterface, SourceLocation, string, string, string}
     */
    private function resolveBranch(TokenStream $tokenStream, Token $openToken, string $unterminatedMessage): array
    {
        $startIndex = $tokenStream->getReadTokenIndex() - 1;
        $phelRange = null;
        $defaultRange = null;
        $phelNode = null;
        $defaultNode = null;

        while ($tokenStream->valid()) {
            $keywordNode = $this->readNonTriviaExpression($tokenStream);
            if (!$keywordNode instanceof NodeInterface) {
                break;
            }

            $formStart = 0;
            $formNode = $this->readNonTriviaExpression($tokenStream, $formStart);
            $formEnd = $tokenStream->getReadTokenIndex() - 1;
            if (!$formNode instanceof NodeInterface) {
                break;
            }

            if ($keywordNode instanceof KeywordNode) {
                $keywordCode = $keywordNode->getCode();
                if ($keywordCode === ':phel') {
                    $phelNode = $formNode;
                    $phelRange = [$formStart, $formEnd];
                } elseif ($keywordCode === ':default') {
                    $defaultNode = $formNode;
                    $defaultRange = [$formStart, $formEnd];
                }
            }
        }

        // Consume the closing paren — throw if the stream is exhausted or missing ')'
        if (!$tokenStream->valid() || $tokenStream->current()->getType() !== Token::T_CLOSE_PARENTHESIS) {
            throw UnfinishedParserException::forSnippet(
                $tokenStream->getCodeSnippet(),
                $openToken,
                $unterminatedMessage,
                ErrorCode::UNTERMINATED_LIST,
            );
        }

        $endIndex = $tokenStream->getReadTokenIndex();
        $code = $tokenStream->getCodeRange($startIndex, $endIndex);
        $selectedRange = $phelRange ?? $defaultRange;
        $prefix = $selectedRange === null ? '' : $tokenStream->getCodeRange($startIndex, $selectedRange[0] - 1);
        $suffix = $selectedRange === null ? '' : $tokenStream->getCodeRange($selectedRange[1] + 1, $endIndex);
        $end = $tokenStream->current()->getEndLocation();
        $tokenStream->next();

        return [$phelNode ?? $defaultNode, $end, $code, $prefix, $suffix];
    }

    /**
     * Reads the next non-trivia expression inside a reader conditional.
     * Returns null when only trivia remains before the closing paren (or EOF),
     * letting the caller exit the branch loop cleanly.
     *
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    private function readNonTriviaExpression(TokenStream $tokenStream, int &$startIndex = 0): ?NodeInterface
    {
        while ($tokenStream->valid()) {
            $tokenType = $tokenStream->current()->getType();

            // End of file stops the scan here so `resolveBranch` can name the
            // reader conditional; reading on would report the enclosing form.
            if ($tokenType === Token::T_CLOSE_PARENTHESIS || $tokenType === Token::T_EOF) {
                return null;
            }

            $startIndex = $tokenStream->getReadTokenIndex();
            $node = $this->parser->readExpression($tokenStream);
            if (!$node instanceof TriviaNodeInterface) {
                return $node;
            }
        }

        return null;
    }
}
