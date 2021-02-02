<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzeSymbol;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use PHPUnit\Framework\TestCase;

final class AnalyzeSymbolTest extends TestCase
{
    private AnalyzeSymbol $symbolAnalyzer;

    public function setUp(): void
    {
        $this->symbolAnalyzer = new AnalyzeSymbol(new Analyzer(new GlobalEnvironment()));
    }

    public function testPhpSymbol(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new PhpVarNode($env, 'is_array', null),
            $this->symbolAnalyzer->analyze(Symbol::createForNamespace('php', 'is_array'), $env)
        );
    }

    public function testLocalVar(): void
    {
        $env = NodeEnvironment::empty()->withLocals([Symbol::create('a')]);
        self::assertEquals(
            new LocalVarNode($env, Symbol::create('a'), null),
            $this->symbolAnalyzer->analyze(Symbol::create('a'), $env)
        );
    }

    public function testLocalShadowedVar(): void
    {
        $env = NodeEnvironment::empty()
            ->withLocals([Symbol::create('a')])
            ->withShadowedLocal(Symbol::create('a'), Symbol::create('b'));

        self::assertEquals(
            new LocalVarNode($env, Symbol::create('b'), null),
            $this->symbolAnalyzer->analyze(Symbol::create('a'), $env)
        );
    }

    public function testGlobalVar(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('test');
        $globalEnv->addDefinition('test', Symbol::create('a'), new Table());
        $symbolAnalyzer = new AnalyzeSymbol(new Analyzer($globalEnv));

        $env = NodeEnvironment::empty();
        self::assertEquals(
            new GlobalVarNode($env, 'test', Symbol::create('a'), new Table(), null),
            $symbolAnalyzer->analyze(Symbol::create('a'), $env)
        );
    }

    public function testUndefinedGlobalVar(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Can not resolve symbol 'a'");

        $env = NodeEnvironment::empty();
        $this->symbolAnalyzer->analyze(Symbol::create('a'), $env);
    }

    public function testLocalVarWinsOverGlobalVar(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('test');
        $globalEnv->addDefinition('test', Symbol::create('a'), new Table());
        $symbolAnalyzer = new AnalyzeSymbol(new Analyzer($globalEnv));

        $env = NodeEnvironment::empty()->withLocals([Symbol::create('a')]);
        self::assertEquals(
            new LocalVarNode($env, Symbol::create('a'), null),
            $symbolAnalyzer->analyze(Symbol::create('a'), $env)
        );
    }
}
