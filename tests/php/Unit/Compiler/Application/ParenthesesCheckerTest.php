<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Application;

use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\ParenthesesChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParenthesesCheckerTest extends TestCase
{
    private ParenthesesChecker $checker;

    private Lexer $lexer;

    protected function setUp(): void
    {
        $this->checker = new ParenthesesChecker();
        $this->lexer = new Lexer();
    }

    #[DataProvider('balancedCases')]
    public function test_balanced_input_is_detected_as_balanced(string $code): void
    {
        self::assertTrue($this->isBalanced($code), $code);
    }

    #[DataProvider('unbalancedCases')]
    public function test_unbalanced_input_is_detected_as_unbalanced(string $code): void
    {
        self::assertFalse($this->isBalanced($code), $code);
    }

    /**
     * A closer with no matching opener cannot be repaired by typing more, so it
     * counts as ready and the parser gets to raise a real error. The REPL
     * buffers until this returns true, so reporting these as "keep waiting"
     * left a mistyped bracket hanging with no feedback at all.
     */
    #[DataProvider('malformedCases')]
    public function test_unrepairable_input_is_handed_to_the_parser(string $code): void
    {
        self::assertTrue($this->isBalanced($code), $code);
    }

    public static function malformedCases(): iterable
    {
        yield 'wrong closer while a list is open' => ["(php/+ 1\n2\n]"];
        yield 'wrong closer while a vector is open' => ['[1 2)'];
        yield 'wrong closer while a map is open' => ['{:a 1]'];
        yield 'wrong closer while a set is open' => ['#{1 2)'];
        yield 'stray closer on its own' => [']'];
        yield 'closer before its opener' => [')('];
    }

    public static function balancedCases(): iterable
    {
        yield 'empty' => [''];
        yield 'plain parens' => ['(+ 1 2)'];
        yield 'nested parens' => ['(+ 1 (* 2 3))'];
        yield 'vector' => ['[1 2 3]'];
        yield 'map' => ['{:a 1 :b 2}'];
        yield 'set literal' => ['#{1 2 3}'];
        yield 'hash fn' => ['#(>= % 18)'];
        yield 'hash fn inside thread' => ["(->> xs\n  (filter #(>= (:age %) 18)))"];
        yield 'pipe fn (deprecated)' => ['|(+ $ 1)'];
        yield 'reader conditional' => ['#?(:phel 1 :default 0)'];
        yield 'reader conditional splicing' => ['[1 #?@(:phel [2 3])]'];
        yield 'mixed brackets and parens' => ['(let [x 1] (+ x 1))'];
        yield 'nested set and map' => ['{:s #{1 2} :v [1 2]}'];
    }

    public static function unbalancedCases(): iterable
    {
        yield 'missing close paren' => ['(+ 1 2'];
        yield 'missing close in hash fn' => ['#(>= % 18'];
        yield 'missing close in pipe fn' => ['|(+ $ 1'];
        yield 'missing close in reader cond' => ['#?(:phel 1'];
        yield 'missing close in reader cond splicing' => ['#?@(:phel [1'];
        yield 'missing close bracket' => ['[1 2 3'];
        yield 'missing close brace' => ['{:a 1'];
        yield 'missing close in set' => ['#{1 2'];
        yield 'multiline thread with hash fn unclosed' => ["(->> xs\n  (filter #(>= (:age %) 18))"];
        yield 'multiline thread still open' => ["(->> users\n  (filter adult?)"];
    }

    private function isBalanced(string $code): bool
    {
        return $this->checker->hasBalancedParentheses($this->lexer->lexString($code));
    }
}
