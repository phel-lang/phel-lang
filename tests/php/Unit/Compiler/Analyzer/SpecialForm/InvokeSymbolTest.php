<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Exception;
use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\ArrayNode;
use Phel\Compiler\Analyzer\Ast\CallNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Ast\VectorNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\InvokeSymbol;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class InvokeSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('user', Symbol::create('my-macro'), TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('macro'), true));
        $GLOBALS['__phel']['user']['my-macro'] = function ($a) {
            return $a;
        };

        $env->addDefinition('user', Symbol::create('my-failed-macro'), TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('macro'), true));
        $GLOBALS['__phel']['user']['my-failed-macro'] = function ($a): void {
            throw new Exception('my-failed-macro message');
        };

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
            TypeFactory::getInstance()->persistentVectorFromArray([PhelArray::create(1)]),
        ]);
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($list, $env);

        $this->assertEquals(
            new VectorNode(
                $env,
                [
                    new ArrayNode(
                        $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(),
                        [
                            new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(), 1),
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
}
