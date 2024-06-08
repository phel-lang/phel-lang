<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Exception;
use Phel\Compiler\Domain\Analyzer\Analyzer;
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
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class InvokeSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        Registry::getInstance()->clear();
        $env = new GlobalEnvironment();
        $env->addDefinition('user', Symbol::create('my-global-var'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-global-var',
            static fn ($a, $b): int => $a + $b,
            TypeFactory::getInstance()->persistentMapFromKVs('min-arity', 2),
        );

        $env->addDefinition('user', Symbol::create('my-macro'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-macro',
            static fn ($a) => $a,
            TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('macro'), true),
        );

        $env->addDefinition('user', Symbol::create('my-failed-macro'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-failed-macro',
            static fn ($a) => throw new Exception('my-failed-macro message'),
            TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('macro'), true),
        );

        $env->addDefinition('user', Symbol::create('my-inline-fn'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-inline-fn',
            static fn ($a): int => 1,
            TypeFactory::getInstance()->persistentMapFromKVs(
                Keyword::create('inline'),
                static fn ($a): int => 2,
            ),
        );

        $env->addDefinition('user', Symbol::create('my-inline-fn-with-arity'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-inline-fn-with-arity',
            static fn ($a, $b): int => 1,
            TypeFactory::getInstance()->persistentMapFromKVs(
                Keyword::create('inline'),
                static fn ($a, $b): int => 2,
                Keyword::create('inline-arity'),
                static fn ($n): bool => $n === 2,
            ),
        );

        $this->analyzer = new Analyzer($env);
    }

    public function test_validate_enough_args_provided(): void
    {
        $env = NodeEnvironment::empty();
        $f = new GlobalVarNode(
            $env->withExpressionContext()->withDisallowRecurFrame(),
            'user',
            Symbol::create('my-global-var'),
            TypeFactory::getInstance()->emptyPersistentMap(),
        );

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::createForNamespace('user', 'my-global-var'),
            '1arg',
        ]);

        $this->expectExceptionObject(AnalyzerException::notEnoughArgsProvided($f, $list, minArity: 2));

        (new InvokeSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_invoke_without_arguments(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
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
        $list = TypeFactory::getInstance()->persistentListFromArray([
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
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::createForNamespace('user', 'my-macro'),
            TypeFactory::getInstance()->persistentVectorFromArray([
                TypeFactory::getInstance()->persistentVectorFromArray([1]),
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
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Error in expanding macro "user\\my-failed-macro": my-failed-macro message');

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::createForNamespace('user', 'my-failed-macro'),
            TypeFactory::getInstance()->persistentVectorFromArray([1]),
        ]);
        $env = NodeEnvironment::empty();
        (new InvokeSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_macro_undefined_macro(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Cannot resolve symbol 'user/my-undefined-macro'");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::createForNamespace('user', 'my-undefined-macro'),
            TypeFactory::getInstance()->persistentVectorFromArray([1]),
        ]);
        $env = NodeEnvironment::empty();
        (new InvokeSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_inline_expand(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
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
        $list = TypeFactory::getInstance()->persistentListFromArray([
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
        $list = TypeFactory::getInstance()->persistentListFromArray([
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
                    TypeFactory::getInstance()->persistentMapFromKVs(
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
}
