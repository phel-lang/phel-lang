<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Application;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Application\NamespaceEnvironmentSerializer;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefaultLangAliasesRegistrar;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\UseAliasRegistrar;
use Phel\Lang\Collections\LazySeq\LazySeq;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class NamespaceEnvironmentSerializerTest extends TestCase
{
    public function test_default_aliases_remain_replaceable_after_roundtrip(): void
    {
        $original = new GlobalEnvironment();
        DefaultLangAliasesRegistrar::register(new Analyzer($original), 'app.core');
        $data = new NamespaceEnvironmentSerializer($original)->capture('app.core');
        self::assertSame(['ns' => null, 'name' => '\\' . LazySeqInterface::class, 'default_alias' => true], $data['use_aliases']['LazySeq']);

        $restored = new GlobalEnvironment();
        new NamespaceEnvironmentSerializer($restored)->restore('app.core', $data);
        new UseAliasRegistrar(new Analyzer($restored))->register('app.core', Phel::list([
            Symbol::create('use'), Symbol::create(LazySeq::class),
        ]));

        self::assertSame('\\' . LazySeq::class, $restored->getUseAliases('app.core')['LazySeq']->getName());
    }

    public function test_explicit_default_targets_remain_explicit_after_roundtrip(): void
    {
        $original = new GlobalEnvironment();
        $analyzer = new Analyzer($original);
        DefaultLangAliasesRegistrar::register($analyzer, 'app.core');
        new UseAliasRegistrar($analyzer)->register('app.core', Phel::list([
            Symbol::create('use'), Symbol::create(LazySeqInterface::class), Keyword::create('as'), Symbol::create('LazySeq'),
        ]));
        $data = new NamespaceEnvironmentSerializer($original)->capture('app.core');
        self::assertArrayNotHasKey('default_alias', $data['use_aliases']['LazySeq']);

        $restored = new GlobalEnvironment();
        new NamespaceEnvironmentSerializer($restored)->restore('app.core', $data);
        $registrar = new UseAliasRegistrar(new Analyzer($restored));
        $registrar->register('app.core', Phel::list([
            Symbol::create('use'), Symbol::create(LazySeqInterface::class), Keyword::create('as'), Symbol::create('LazySeq'),
        ]));

        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('cannot also name \\' . LazySeq::class);
        $registrar->register('app.core', Phel::list([
            Symbol::create('use'), Symbol::create(LazySeq::class),
        ]));
    }
}
