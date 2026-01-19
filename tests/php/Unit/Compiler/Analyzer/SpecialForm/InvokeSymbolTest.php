<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Exception;
use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Application\Munge;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InvokeSymbol;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class InvokeSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        Phel::clear();
        $env = new GlobalEnvironment();
        $env->addDefinition('user', Symbol::create('my-global-fn'));
        Phel::addDefinition(
            'user',
            'my-global-fn',
            static fn ($a, $b): int => $a + $b,
            Phel::map('min-arity', 2, 'is-variadic', false),
        );

        $env->addDefinition('user', Symbol::create('my-variadic-fn'));
        Phel::addDefinition(
            'user',
            'my-variadic-fn',
            static fn ($a, ...$rest): int => $a,
            Phel::map('min-arity', 1, 'is-variadic', true),
        );

        $env->addDefinition('user', Symbol::create('my-bounded-fn'));
        Phel::addDefinition(
            'user',
            'my-bounded-fn',
            static fn ($a, $b = null): int => $a,
            Phel::map('min-arity', 1, 'is-variadic', false, 'max-arity', 2),
        );

        $env->addDefinition('user', Symbol::create('my-macro'));
        Phel::addDefinition(
            'user',
            'my-macro',
            static fn ($a) => $a,
            Phel::map(Keyword::create('macro'), true),
        );

        $env->addDefinition('user', Symbol::create('my-failed-macro'));
        Phel::addDefinition(
            'user',
            'my-failed-macro',
            static fn ($a) => throw new Exception('my-failed-macro message'),
            Phel::map(Keyword::create('macro'), true),
        );

        $env->addDefinition('user', Symbol::create('my-inline-fn'));
        Phel::addDefinition(
            'user',
            'my-inline-fn',
            static fn ($a): int => 1,
            Phel::map(
                Keyword::create('inline'),
                static fn ($a): int => 2,
            ),
        );

        $env->addDefinition('user', Symbol::create('my-inline-fn-with-arity'));
        Phel::addDefinition(
            'user',
            'my-inline-fn-with-arity',
            static fn ($a, $b): int => 1,
            Phel::map(
                Keyword::create('inline'),
                static fn ($a, $b): int => 2,
                Keyword::create('inline-arity'),
                static fn ($n): bool => $n === 2,
            ),
        );

        $this->analyzer = new Analyzer($env);
    }

    public function test_not_enough_args_provided_then_error(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Wrong number of arguments to function "user\\my-global-fn". Got: 1. Expected: 2');

        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-global-fn'),
            '1arg',
        ]);

        (new InvokeSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_variadic_function_error_message(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Wrong number of arguments to function "user\\my-variadic-fn". Got: 0. Expected: at least 1');

        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-variadic-fn'),
        ]);

        (new InvokeSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_bounded_function_too_few_args(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Wrong number of arguments to function "user\\my-bounded-fn". Got: 0. Expected: 1 or 2');

        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-bounded-fn'),
        ]);

        (new InvokeSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_bounded_function_too_many_args(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Wrong number of arguments to function "user\\my-bounded-fn". Got: 3. Expected: 1 or 2');

        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-bounded-fn'),
            '1arg',
            '2arg',
            '3arg',
        ]);

        (new InvokeSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_valid_enough_args_provided(): void
    {
        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-global-fn'),
            '1arg',
            '2arg',
        ]);

        (new InvokeSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        $this->expectNotToPerformAssertions();
    }

    public function test_invoke_without_arguments(): void
    {
        $list = Phel::list([
            Symbol::createForNamespace('php', '+'),
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);

        $this->assertEquals(
            new CallNode(
                $env,
                new PhpVarNode($env->withExpressionContext()->withDisallowRecurFrame(), '+'),
                [],
            ),
            $node,
        );
    }

    public function test_invoke_with_one_arguments(): void
    {
        $list = Phel::list([
            Symbol::createForNamespace('php', '+'),
            1,
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);

        $this->assertEquals(
            new CallNode(
                $env,
                new PhpVarNode($env->withExpressionContext()->withDisallowRecurFrame(), '+'),
                [
                    new LiteralNode($env->withExpressionContext()->withDisallowRecurFrame(), 1),
                ],
            ),
            $node,
        );
    }

    public function test_macro_expand(): void
    {
        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-macro'),
            Phel::vector([
                Phel::vector([1]),
            ]),
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);

        $this->assertEquals(
            new VectorNode(
                $env,
                [
                    new VectorNode(
                        $env->withExpressionContext()->withDisallowRecurFrame(),
                        [
                            new LiteralNode($env->withExpressionContext()->withDisallowRecurFrame()->withDisallowRecurFrame(), 1),
                        ],
                    ),

                ],
            ),
            $node,
        );
    }

    public function test_macro_expand_failure(): void
    {
        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-failed-macro'),
            Phel::vector([1]),
        ]);
        $env = NodeEnvironment::empty();

        try {
            (new InvokeSymbol($this->analyzer))->analyze($list, $env);
            self::fail('Expected AnalyzerException to be thrown');
        } catch (AnalyzerException $analyzerException) {
            self::assertStringContainsString('Error in expanding macro "user\\my-failed-macro"', $analyzerException->getMessage());
            self::assertStringContainsString('Expanding: (my-failed-macro [1])', $analyzerException->getMessage());
            self::assertStringContainsString('Cause: my-failed-macro message', $analyzerException->getMessage());
        }
    }

    public function test_macro_undefined_macro(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Cannot resolve symbol 'user/my-undefined-macro'");

        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-undefined-macro'),
            Phel::vector([1]),
        ]);
        $env = NodeEnvironment::empty();
        (new InvokeSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_inline_expand(): void
    {
        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-inline-fn'),
            'foo',
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);

        $this->assertEquals(
            new LiteralNode($env->withStatementContext(), 2),
            $node,
        );
    }

    public function test_inline_expand_with_arity_check(): void
    {
        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-inline-fn-with-arity'),
            'foo', 'bar',
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);

        $this->assertEquals(
            new LiteralNode($env->withStatementContext(), 2),
            $node,
        );
    }

    public function test_inline_expand_with_arity_check_failed(): void
    {
        $list = Phel::list([
            Symbol::createForNamespace('user', 'my-inline-fn-with-arity'),
            'foo',
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);

        $this->assertEquals(
            new CallNode(
                $env,
                new GlobalVarNode(
                    $env->withExpressionContext()->withDisallowRecurFrame(),
                    'user',
                    Symbol::create('my-inline-fn-with-arity'),
                    Phel::map(
                        Keyword::create('inline'),
                        static fn ($a, $b): int => 2,
                        Keyword::create('inline-arity'),
                        static fn ($n): bool => $n === 2,
                    ),
                ),
                [
                    new LiteralNode($env->withExpressionContext()->withDisallowRecurFrame(), 'foo'),
                ],
            ),
            $node,
        );
    }

    public function test_macro_expand_with_hyphenated_namespace(): void
    {
        $ns = 'cli-skeleton\\macro-demo';
        $macroName = 'my-hyphen-macro';

        $this->analyzer->addDefinition($ns, Symbol::create($macroName));

        $mungedNs = (new Munge())->encodeNs($ns);
        Phel::addDefinition(
            $mungedNs,
            $macroName,
            static fn ($x) => $x,
            Phel::map(Keyword::create('macro'), true),
        );

        $list = Phel::list([
            Symbol::createForNamespace($ns, $macroName),
            'foo',
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);

        $this->assertEquals(
            new LiteralNode($env->withStatementContext(), 'foo'),
            $node,
        );
    }
}
