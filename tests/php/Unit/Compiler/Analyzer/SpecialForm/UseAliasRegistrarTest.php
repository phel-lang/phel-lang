<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefaultLangAliasesRegistrar;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\UseAliasRegistrar;
use Phel\Lang\Collections\LazySeq\LazySeq;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function define;

final class UseAliasRegistrarTest extends TestCase
{
    public function test_explicit_imports_may_replace_default_aliases(): void
    {
        $globalEnv = new GlobalEnvironment();
        $analyzer = new Analyzer($globalEnv);
        DefaultLangAliasesRegistrar::register($analyzer, 'app.core');
        new UseAliasRegistrar($analyzer)->register('app.core', Phel::list([
            Symbol::create('use'), Symbol::create(LazySeq::class),
        ]));

        self::assertSame('\\' . LazySeq::class, $globalEnv->getUseAliases('app.core')['LazySeq']->getName());
    }

    #[DataProvider('markedImports')]
    public function test_explicit_imports_cannot_keep_default_origin(string $class, string $alias): void
    {
        $globalEnv = new GlobalEnvironment();
        $analyzer = new Analyzer($globalEnv);
        DefaultLangAliasesRegistrar::register($analyzer, 'app.core');
        $marked = Symbol::create('\\' . $class)->withMeta($globalEnv->getUseAliases('app.core')['LazySeq']->getMeta());
        $registrar = new UseAliasRegistrar($analyzer);
        $registrar->register('app.core', Phel::list([
            Symbol::create('use'), $marked, Keyword::create('as'), Symbol::create($alias),
        ]));

        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('cannot also name \\DateTimeImmutable');
        $registrar->register('app.core', Phel::list([
            Symbol::create('use'), Symbol::create('DateTimeImmutable'), Keyword::create('as'), Symbol::create($alias),
        ]));
    }

    public static function markedImports(): iterable
    {
        yield 'explicit same default target' => [LazySeqInterface::class, 'LazySeq'];
        yield 'forged default origin' => ['DateTime', 'd'];
    }

    public function test_an_explicit_import_of_a_default_target_cannot_be_replaced(): void
    {
        $registrar = new UseAliasRegistrar(new Analyzer(new GlobalEnvironment()));
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('cannot also name \\' . LazySeq::class);

        $registrar->register('app.core', Phel::list([
            Symbol::create('use'), Symbol::create(LazySeqInterface::class), Keyword::create('as'), Symbol::create('LazySeq'),
            Symbol::create(LazySeq::class),
        ]));
    }

    #[DataProvider('equivalentImports')]
    public function test_equivalent_imports_may_repeat(string $first, string $second): void
    {
        $globalEnv = new GlobalEnvironment();
        $registrar = new UseAliasRegistrar(new Analyzer($globalEnv));
        foreach ([$first, $second] as $class) {
            $registrar->register('app.core', Phel::list([
                Keyword::create('use'), Symbol::create($class), Keyword::create('as'), Symbol::create('d'),
            ]));
        }

        self::assertArrayHasKey('d', $globalEnv->getUseAliases('app.core'));
    }

    public static function equivalentImports(): iterable
    {
        yield 'same class' => ['DateTime', 'DateTime'];
        yield 'PHP class casing' => ['DateTime', 'datetime'];
        yield 'dot and backslash class spelling' => ['Phel.Lang.Symbol', Symbol::class];
    }

    public function test_alias_names_remain_case_sensitive(): void
    {
        $globalEnv = new GlobalEnvironment();
        $registrar = new UseAliasRegistrar(new Analyzer($globalEnv));
        $registrar->register('app.core', Phel::list([
            Keyword::create('use'), Symbol::create('DateTime'), Keyword::create('as'), Symbol::create('d'),
            Symbol::create('DateTimeImmutable'), Keyword::create('as'), Symbol::create('D'),
        ]));

        self::assertSame('\\DateTime', $globalEnv->getUseAliases('app.core')['d']->getName());
        self::assertSame('\\DateTimeImmutable', $globalEnv->getUseAliases('app.core')['D']->getName());
    }

    public function test_imported_constants_with_different_casing_are_distinct(): void
    {
        $first = __NAMESPACE__ . '\\useAliasCase';
        $second = __NAMESPACE__ . '\\USEALIASCASE';
        define($first, 1);
        define($second, 2);
        $registrar = new UseAliasRegistrar(new Analyzer(new GlobalEnvironment()));

        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('cannot also name \\' . $second);

        $registrar->register('app.core', Phel::list([
            Keyword::create('use'), Symbol::create($first), Keyword::create('as'), Symbol::create('d'),
            Symbol::create($second), Keyword::create('as'), Symbol::create('d'),
        ]));
    }

    public function test_a_different_import_cannot_replace_an_alias(): void
    {
        $globalEnv = new GlobalEnvironment();
        $registrar = new UseAliasRegistrar(new Analyzer($globalEnv));
        $registrar->register('app.core', Phel::list([
            Keyword::create('use'), Symbol::create('DateTime'), Keyword::create('as'), Symbol::create('d'),
        ]));

        $alias = Symbol::create('d');
        $alias->setStartLocation(new SourceLocation('/app/core.phel', 3, 28));

        try {
            $registrar->register('app.core', Phel::list([
                Keyword::create('use'), Symbol::create('DateTimeImmutable'), Keyword::create('as'), $alias,
            ]));
            self::fail('Expected an AnalyzerException.');
        } catch (AnalyzerException $analyzerException) {
            self::assertSame("Alias 'd' already names \\DateTime in app.core, so it cannot also name \\DateTimeImmutable.", $analyzerException->getMessage());
            self::assertSame(ErrorCode::DUPLICATE_DEFINITION, $analyzerException->getErrorCode());
            self::assertSame(3, $analyzerException->getStartLocation()?->getLine());
            self::assertSame(28, $analyzerException->getStartLocation()?->getColumn());
            self::assertSame('\\DateTime', $globalEnv->getUseAliases('app.core')['d']->getName());
        }
    }
}
