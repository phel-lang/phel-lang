<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;

/**
 * This rule removes all indentations. It is used as a preprocessor for the IndentRule.
 */
final class UnindentRule implements RuleInterface
{
    public function transform(NodeInterface $node): NodeInterface
    {
        return $this->unident(ParseTreeZipper::createRoot($node));
    }

    /**
     * @throws ZipperException
     */
    private function unident(ParseTreeZipper $form): NodeInterface
    {
        $node = $form;
        while (!$node->isEnd()) {
            $node = $node->next();
            if ($this->shouldUnindent($node)) {
                $node = $node->remove();
            }
        }

        return $node->root();
    }

    private function shouldUnindent(ParseTreeZipper $form): bool
    {
        return $this->isIndention($form) && !$this->isNextComment($form);
    }

    private function skipWhitespace(ParseTreeZipper $form): ParseTreeZipper
    {
        $node = $form;
        while ($node->isWhitespace()) {
            $node = $node->next();
        }

        return $node;
    }

    private function isNextComment(ParseTreeZipper $form): bool
    {
        return $this->skipWhitespace($form->next())->isComment();
    }

    private function isIndention(ParseTreeZipper $form): bool
    {
        try {
            return $form->prev()->isLineBreak() && $form->isWhitespace();
        } catch (ZipperException $e) {
            return false;
        }
    }
}
