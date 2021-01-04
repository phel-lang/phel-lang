<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Exception;
use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\ArrayNode;
use Phel\Compiler\Analyzer\Ast\CallNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Ast\TupleNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\InvokeSymbol;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class InvokeSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('user', Symbol::create('my-macro'), Table::fromKVs(new Keyword('macro'), true));
        $GLOBALS['__phel']['user']['my-macro'] = function ($a) {
            return $a;
        };

        $env->addDefinition('user', Symbol::create('my-failed-macro'), Table::fromKVs(new Keyword('macro'), true));
        $GLOBALS['__phel']['user']['my-failed-macro'] = function ($a) {
            throw new Exception('my-failed-macro message');
        };

        $this->analyzer = new Analyzer($env);
    }

    public function testInvokeWithoutArguments(): void
    {
        $tuple = Tuple::create(
            Symbol::createForNamespace('php', '+')
        );
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($tuple, $env);

        $this->assertEquals(
            new CallNode(
                $env,
                new PhpVarNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(), '+'),
                []
            ),
            $node
        );
    }

    public function testInvokeWithOneArguments(): void
    {
        $tuple = Tuple::create(
            Symbol::createForNamespace('php', '+'),
            1
        );
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($tuple, $env);

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

    public function testMacroExpand(): void
    {
        $tuple = Tuple::create(
            Symbol::createForNamespace('user', 'my-macro'),
            Tuple::createBracket(PhelArray::create(1))
        );
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($tuple, $env);

        $this->assertEquals(
            new TupleNode(
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

    public function testMacroExpandFailure(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Error in expanding macro "user\\my-failed-macro": my-failed-macro message');

        $tuple = Tuple::create(
            Symbol::createForNamespace('user', 'my-failed-macro'),
            Tuple::createBracket(1)
        );
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($tuple, $env);
    }

    public function testMacroUndefinedMacro(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Can not resolve symbol \'user/my-undefined-macro\'');

        $tuple = Tuple::create(
            Symbol::createForNamespace('user', 'my-undefined-macro'),
            Tuple::createBracket(1)
        );
        $env = NodeEnvironment::empty();
        $node = (new InvokeSymbol($this->analyzer))->analyze($tuple, $env);
    }
}
