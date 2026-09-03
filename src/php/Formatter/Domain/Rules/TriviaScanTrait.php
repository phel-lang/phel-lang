<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;

/**
 * The lookahead both indentation rules need: a comment's own leading
 * whitespace is left alone, so each has to see past the whitespace run
 * before deciding.
 *
 * @internal
 */
trait TriviaScanTrait
{
    private function isNextComment(ParseTreeZipper $form): bool
    {
        return $this->skipWhitespace($form->next())->isComment();
    }

    private function skipWhitespace(ParseTreeZipper $form): ParseTreeZipper
    {
        $node = $form;
        while ($node->isWhitespace()) {
            /** @var ParseTreeZipper $node */
            $node = $node->next();
        }

        return $node;
    }
}
