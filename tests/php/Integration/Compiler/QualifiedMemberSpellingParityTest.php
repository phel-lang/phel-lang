<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Iterator;
use Phel\Compiler\Domain\Analyzer\QualifiedMemberSyntax;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

/**
 * `QualifiedMemberSyntax` states in PHP which symbols name a PHP class member,
 * and `phel.core/set!` restates it in Phel, because the standard library cannot
 * import an `@internal` compiler class. Nothing but this test keeps the two
 * copies from drifting: a symbol the analyzer reads as a class member has to
 * reach `set!`'s property branch, and anything else has to stay a var.
 */
final class QualifiedMemberSpellingParityTest extends AbstractCompilerRuntimeTestCase
{
    /**
     * @return Iterator<int<0, max>, array{string, string, bool}>
     */
    public static function providerSpellings(): Iterator
    {
        yield 'absolute class name' => ['\\Foo', 'slot', true];
        yield 'bare class name' => ['Foo', 'slot', true];
        yield 'sigil names the static property' => ['Foo', '$slot', true];
        yield 'underscore opens a PHP identifier' => ['Foo', '_slot', true];
        yield 'earmuffed var is no PHP member' => ['Foo', '*x*', false];
        yield 'kebab name is no PHP member' => ['Foo', '-x', false];
        yield 'lower-case namespace is a Phel namespace' => ['my-ns', 'slot', false];
        yield 'php is the host-function prefix' => ['php', 'slot', false];
    }

    #[DataProvider('providerSpellings')]
    public function test_both_copies_of_the_rule_agree(string $ns, string $name, bool $isMember): void
    {
        self::assertSame(
            $isMember,
            QualifiedMemberSyntax::isClassReference($ns)
                && (QualifiedMemberSyntax::isMemberName($name) || QualifiedMemberSyntax::isStaticPropertyName($name)),
            'the analyzer disagrees with the expected reading',
        );

        // `let` heads the property branch (it binds the value once), `var-set`
        // the var branch. Asserting on the expansion keeps the check away from
        // whether the class or the var happens to exist.
        $head = $this->compilerFacade->eval(
            sprintf("(str (first (macroexpand '(set! %s/%s 1))))", $ns, $name),
            new CompileOptions(),
        );

        self::assertSame(
            $isMember ? 'let' : 'phel.core/var-set',
            $head,
            'set! took the other branch',
        );
    }
}
