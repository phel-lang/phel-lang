<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpCallableNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzeSymbol;
use Phel\Lang\Symbol;
use PhelTest\Support\Fixtures\PhpInterop\QualifiedMemberFixture;
use PHPUnit\Framework\TestCase;

final class AnalyzeSymbolTest extends TestCase
{
    private AnalyzeSymbol $symbolAnalyzer;

    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
        $this->symbolAnalyzer = new AnalyzeSymbol($this->analyzer);
        Phel::clear();
    }

    public function test_php_symbol(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new PhpVarNode($env, 'is_array'),
            $this->symbolAnalyzer->analyze(Symbol::createForNamespace('php', 'is_array'), $env),
        );
    }

    public function test_local_var(): void
    {
        $env = NodeEnvironment::empty()->withLocals([Symbol::create('a')]);
        self::assertEquals(
            new LocalVarNode($env, Symbol::create('a')),
            $this->symbolAnalyzer->analyze(Symbol::create('a'), $env),
        );
    }

    public function test_local_shadowed_var(): void
    {
        $env = NodeEnvironment::empty()
            ->withLocals([Symbol::create('a')])
            ->withShadowedLocal(Symbol::create('a'), Symbol::create('b'));

        self::assertEquals(
            new LocalVarNode($env, Symbol::create('b')),
            $this->symbolAnalyzer->analyze(Symbol::create('a'), $env),
        );
    }

    public function test_global_var(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('test');
        $globalEnv->addDefinition('test', Symbol::create('a'));

        $symbolAnalyzer = new AnalyzeSymbol(new Analyzer($globalEnv));

        $env = NodeEnvironment::empty();
        self::assertEquals(
            new GlobalVarNode($env, 'test', Symbol::create('a'), Phel::map()),
            $symbolAnalyzer->analyze(Symbol::create('a'), $env),
        );
    }

    public function test_undefined_global_var(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Cannot resolve symbol 'a'");

        $env = NodeEnvironment::empty();
        $this->symbolAnalyzer->analyze(Symbol::create('a'), $env);
    }

    public function test_local_var_wins_over_global_var(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('test');
        $globalEnv->addDefinition('test', Symbol::create('a'));

        $symbolAnalyzer = new AnalyzeSymbol(new Analyzer($globalEnv));

        $env = NodeEnvironment::empty()->withLocals([Symbol::create('a')]);
        self::assertEquals(
            new LocalVarNode($env, Symbol::create('a')),
            $symbolAnalyzer->analyze(Symbol::create('a'), $env),
        );
    }

    public function test_qualified_symbol_is_not_shadowed_by_a_local_of_the_same_short_name(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('test');
        $globalEnv->addDefinition('other', Symbol::create('a'));

        $symbolAnalyzer = new AnalyzeSymbol(new Analyzer($globalEnv));

        $env = NodeEnvironment::empty()->withLocals([Symbol::create('a')]);
        self::assertEquals(
            new GlobalVarNode($env, 'other', Symbol::create('a'), Phel::map()),
            $symbolAnalyzer->analyze(Symbol::createForNamespace('other', 'a'), $env),
        );
    }

    public function test_qualified_symbol_of_the_current_ns_is_not_shadowed_by_a_local(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('test');
        $globalEnv->addDefinition('test', Symbol::create('a'));

        $symbolAnalyzer = new AnalyzeSymbol(new Analyzer($globalEnv));

        $env = NodeEnvironment::empty()->withLocals([Symbol::create('a')]);
        self::assertEquals(
            new GlobalVarNode($env, 'test', Symbol::create('a'), Phel::map()),
            $symbolAnalyzer->analyze(Symbol::createForNamespace('test', 'a'), $env),
        );
    }

    public function test_undefined_symbol_with_did_you_mean_suggestion(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('test');
        $globalEnv->addDefinition('test', Symbol::create('print'));

        $symbolAnalyzer = new AnalyzeSymbol(new Analyzer($globalEnv));

        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Cannot resolve symbol 'prnt'. Did you mean 'print'?");

        $env = NodeEnvironment::empty();
        $symbolAnalyzer->analyze(Symbol::create('prnt'), $env);
    }

    public function test_undefined_symbol_without_suggestion_when_no_similar_symbols(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('test');
        $globalEnv->addDefinition('test', Symbol::create('foo'));

        $symbolAnalyzer = new AnalyzeSymbol(new Analyzer($globalEnv));

        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Cannot resolve symbol 'zzzzzzzzzzz'");

        $env = NodeEnvironment::empty();
        $symbolAnalyzer->analyze(Symbol::create('zzzzzzzzzzz'), $env);
    }

    public function test_fqn_class_slash_member_shorthand_expands_to_static_call(): void
    {
        $env = NodeEnvironment::empty();
        $node = $this->analyzer->analyze(
            Symbol::createForNamespace('\\DateTimeImmutable', 'ATOM'),
            $env,
        );

        self::assertInstanceOf(PhpObjectCallNode::class, $node);
        self::assertTrue($node->isStatic());
    }

    public function test_lowercase_namespace_does_not_expand_to_static_call(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Cannot resolve symbol 'foo/bar'");

        $env = NodeEnvironment::empty();
        $this->symbolAnalyzer->analyze(Symbol::createForNamespace('foo', 'bar'), $env);
    }

    public function test_static_method_in_value_position_becomes_a_callable(): void
    {
        $env = NodeEnvironment::empty();
        $node = $this->analyzer->analyze(
            Symbol::createForNamespace('\\' . QualifiedMemberFixture::class, 'upper'),
            $env,
        );

        self::assertInstanceOf(PhpCallableNode::class, $node);
        self::assertTrue($node->isStatic());
        self::assertSame('upper', $node->getName());
    }

    public function test_a_constant_beats_a_static_method_of_the_same_name(): void
    {
        $env = NodeEnvironment::empty();
        $node = $this->analyzer->analyze(
            Symbol::createForNamespace('\\' . QualifiedMemberFixture::class, 'new'),
            $env,
        );

        self::assertInstanceOf(PhpObjectCallNode::class, $node);
        self::assertTrue($node->isStatic());
    }

    public function test_instance_method_in_value_position_becomes_a_fn_of_the_receiver(): void
    {
        $env = NodeEnvironment::empty();
        $node = $this->analyzer->analyze(
            Symbol::createForNamespace('\\' . QualifiedMemberFixture::class, '.label'),
            $env,
        );

        self::assertInstanceOf(FnNode::class, $node);
        self::assertTrue($node->isVariadic());
        self::assertCount(2, $node->getParams());
    }

    public function test_an_unknown_member_still_reads_as_a_constant(): void
    {
        $env = NodeEnvironment::empty();
        $node = $this->analyzer->analyze(
            Symbol::createForNamespace('\\' . QualifiedMemberFixture::class, 'MISSING'),
            $env,
        );

        self::assertInstanceOf(PhpObjectCallNode::class, $node);
        self::assertTrue($node->isStatic());
    }

    public function test_a_member_of_an_unknown_class_still_reads_as_a_constant(): void
    {
        $env = NodeEnvironment::empty();
        $node = $this->analyzer->analyze(
            Symbol::createForNamespace('\\NotLoaded\\Nowhere', 'member'),
            $env,
        );

        self::assertInstanceOf(PhpObjectCallNode::class, $node);
        self::assertTrue($node->isStatic());
    }
}
