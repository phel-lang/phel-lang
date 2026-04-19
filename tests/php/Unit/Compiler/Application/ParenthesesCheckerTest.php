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
