<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Domain\Rules\Indenter;

use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\KeywordNode;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NewlineNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\NumberNode;
use Phel\Shared\Parser\Node\SymbolNode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\WhitespaceNode;
use PHPUnit\Framework\TestCase;

abstract class IndenterTestCase extends TestCase
{
    final protected function symbol(string $name): SymbolNode
    {
        return new SymbolNode($name, $this->loc(), $this->loc(), Symbol::create($name));
    }

    final protected function keyword(string $name): KeywordNode
    {
        return new KeywordNode(':' . $name, $this->loc(), $this->loc(), Keyword::create($name));
    }

    final protected function number(string $code): NumberNode
    {
        return new NumberNode($code, $this->loc(), $this->loc(), (int) $code);
    }

    final protected function whitespace(string $code = ' '): WhitespaceNode
    {
        return new WhitespaceNode($code, $this->loc(), $this->loc());
    }

    final protected function newline(): NewlineNode
    {
        return new NewlineNode("\n", $this->loc(), $this->loc());
    }

    /**
     * @param list<NodeInterface> $children
     */
    final protected function listNode(array $children): ListNode
    {
        return new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(), $this->loc(), $children);
    }

    final protected function loc(): SourceLocation
    {
        return new SourceLocation('', 0, 0);
    }
}
