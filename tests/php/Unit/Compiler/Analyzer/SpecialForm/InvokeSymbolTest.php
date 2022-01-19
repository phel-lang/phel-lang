<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Exception;
use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\CallNode;
use Phel\Compiler\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Ast\VectorNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\InvokeSymbol;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class InvokeSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        Registry::getInstance()->clear();
        $env = new GlobalEnvironment();
        $env->addDefinition('user', Symbol::create('my-macro'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-macro',
            fn ($a) => $a,
            TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('macro'), true)
        );

        $env->addDefinition('user', Symbol::create('my-failed-macro'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-failed-macro',
            fn ($a) => throw new Exception('my-failed-macro message'),
            TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('macro'), true)
        );

        $env->addDefinition('user', Symbol::create('my-inline-fn'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-inline-fn',
            fn ($a) => 1,
            TypeFactory::getInstance()->persistentMapFromKVs(
                Keyword::create('inline'),
                fn ($a) => 2
            )
        );

        $env->addDefinition('user', Symbol::create('my-inline-fn-with-arity'));
        Registry::getInstance()->addDefinition(
            'user',
            'my-inline-fn-with-arity',
            fn ($a, $b) => 1,
            TypeFactory::getInstance()->persistentMapFromKVs(
                Keyword::create('inline'),
                fn ($a, $b) => 2,
                Keyword::create('inline-arity'),
                fn ($n) => $n === 2
            )
        );

        $this->analyzer = new Analyzer($env);
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
                new PhpVarNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(), '+'),
                []
            ),
            $node
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
                new PhpVarNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(), '+'),
                [
                    new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(), 1),
                ]
            ),
            $node
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
                        $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(),
                        [
                            new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame()->withDisallowRecurFrame(), 1),
                        ]
                    ),

                ]
            ),
            $node
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
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_macro_undefined_macro(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Cannot resolve symbol \'user/my-undefined-macro\'');

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::createForNamespace('user', 'my-undefined-macro'),
            TypeFactory::getInstance()->persistentVectorFromArray([1]),
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);
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
            new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_STATEMENT), 2),
            $node
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
            new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_STATEMENT), 2),
            $node
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
                    $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(),
                    'user',
                    Symbol::create('my-inline-fn-with-arity'),
                    TypeFactory::getInstance()->persistentMapFromKVs(
                        Keyword::create('inline'),
                        fn ($a, $b) => 2,
                        Keyword::create('inline-arity'),
                        fn ($n) => $n === 2
                    )
                ),
                [
                    new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(), 'foo'),
                ]
            ),
            $node
        );
    }
}
