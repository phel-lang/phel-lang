<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\MetaNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\WhitespaceNode;
use Phel\Formatter\Domain\Rules\Indenter\IndenterInterface;
use Phel\Formatter\Domain\Rules\Indenter\LineIndenter;
use Phel\Formatter\Domain\Rules\Indenter\ListIndenter;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Lang\SourceLocation;

final readonly class IndentRule implements RuleInterface
{
    private const int INDENT_WIDTH = 2;

    private ListIndenter $listIndenter;

    private LineIndenter $lineIndenter;

    /**
     * @param list<IndenterInterface> $indenters
     */
    public function __construct(
        private array $indenters,
    ) {
        $this->listIndenter = new ListIndenter();
        $this->lineIndenter = new LineIndenter();
    }

    public function transform(NodeInterface $node): NodeInterface
    {
        return $this->indent(ParseTreeZipper::createRoot($node));
    }

    private function indent(ParseTreeZipper $form): NodeInterface
    {
        $node = $form;
        while (!$node->isEnd()) {
            $node = $node->next();
            if ($this->shouldIndent($node)) {
                $node = $this->indentLine($node);
            }
        }

        return $node->root();
    }

    private function shouldIndent(ParseTreeZipper $form): bool
    {
        return $form->isLineBreak()
            && !$this->isNextComment($form);
    }

    private function isNextComment(ParseTreeZipper $form): bool
    {
        return $this->skipWhitespace($form->next())->isComment();
    }

    private function skipWhitespace(ParseTreeZipper $form): ParseTreeZipper
    {
        $node = $form;
        while ($node->isWhitespace()) {
            $node = $node->next();
        }

        return $node;
    }

    private function indentLine(ParseTreeZipper $form): ParseTreeZipper
    {
        $width = $this->indentAmount($form);
        if ($width && $width > 0) {
            return $form->insertRight(
                new WhitespaceNode(
                    str_repeat(' ', $width),
                    new SourceLocation('', 0, 0),
                    new SourceLocation('', 0, 0),
                ),
            );
        }

        return $form;
    }

    private function indentAmount(ParseTreeZipper $form): ?int
    {
        $parent = $form->up();
        $parentNode = $parent->getNode();

        if ($parentNode instanceof MetaNode) {
            return $this->indentAmount($form->up());
        }

        if ($parentNode instanceof ListNode
            && $parentNode->getTokenType() === Token::T_OPEN_PARENTHESIS
        ) {
            return $this->customIndent($form);
        }

        return $this->lineIndenter->getMargin($form->leftMost(), self::INDENT_WIDTH);
    }

    private function customIndent(ParseTreeZipper $form): int
    {
        foreach ($this->indenters as $indenter) {
            $margin = $indenter->getMargin($form, self::INDENT_WIDTH);
            if ($margin) {
                return $margin;
            }
        }

        return $this->listIndenter->getMargin($form, self::INDENT_WIDTH);
    }
}
