<?php

declare(strict_types=1);

namespace Phel\Formatter\Rules;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\WhitespaceNode;
use Phel\Formatter\ParseTreeZipper;
use Phel\Formatter\Rules\Indenter\BlockIndenter;
use Phel\Formatter\Rules\Indenter\IndenterInterface;
use Phel\Formatter\Rules\Indenter\InnerIndenter;
use Phel\Formatter\Rules\Indenter\LineIndenter;
use Phel\Formatter\Rules\Indenter\ListIndenter;
use Phel\Lang\SourceLocation;

final class IndentRule implements RuleInterface
{
    private const INDENT_WIDTH = 2;

    /** @var IndenterInterface[] */
    private $customIndenter = [];
    private ListIndenter $listIndenter;
    private LineIndenter $lineIndenter;

    public function __construct()
    {
        $this->customIndenter = [
            new InnerIndenter('def', 0),
            new InnerIndenter('def-', 0),
            new InnerIndenter('defn', 0),
            new InnerIndenter('defn-', 0),
            new InnerIndenter('defmacro', 0),
            new InnerIndenter('defmacro-', 0),
            new InnerIndenter('deftest', 0),
            new InnerIndenter('fn', 0),

            new BlockIndenter('catch', 2),
            new BlockIndenter('do', 0),
            new BlockIndenter('if', 1),
            new BlockIndenter('if-not', 1),
            new BlockIndenter('foreach', 1),
            new BlockIndenter('for', 1),
            new BlockIndenter('dofor', 1),
            new BlockIndenter('let', 1),
            new BlockIndenter('ns', 1),
            new BlockIndenter('loop', 1),
            new BlockIndenter('case', 1),
            new BlockIndenter('cond', 0),
            new BlockIndenter('try', 0),
            new BlockIndenter('finally', 0),
            new BlockIndenter('when', 1),
            new BlockIndenter('when-not', 1),
        ];

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

    private function indentLine(ParseTreeZipper $form): ParseTreeZipper
    {
        $width = $this->indentAmount($form);
        if ($width && $width > 0) {
            return $form->insertRight(
                new WhitespaceNode(str_repeat(' ', $width), new SourceLocation('', 0, 0), new SourceLocation('', 0, 0))
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

        if ($parentNode instanceof ListNode && $parentNode->getTokenType() == Token::T_OPEN_PARENTHESIS) {
            return $this->customIndent($form);
        }

        return $this->lineIndenter->getMargin($form->leftMost(), self::INDENT_WIDTH);
    }

    private function customIndent(ParseTreeZipper $form): ?int
    {
        foreach ($this->customIndenter as $idx => $indenter) {
            $margin = $indenter->getMargin($form, self::INDENT_WIDTH);
            if ($margin) {
                return $margin;
            }
        }

        return $this->listIndenter->getMargin($form, self::INDENT_WIDTH);
    }

    private function shouldIndent(ParseTreeZipper $form): bool
    {
        return $form->isLineBreak() && !$this->isNextComment($form);
    }

    private function skipWhitespace(ParseTreeZipper $form): ParseTreeZipper
    {
        $node = $form;
        while ($node && $node->isWhitespace()) {
            $node = $node->next();
        }

        return $node;
    }

    private function isNextComment(ParseTreeZipper $form): bool
    {
        return $this->skipWhitespace($form->next())->isComment();
    }
}
