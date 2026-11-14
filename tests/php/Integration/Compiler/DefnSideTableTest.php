<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\DataProvider;

final class DefnSideTableTest extends AbstractCompilerRuntimeTestCase
{
    #[DataProvider('sourceSignatures')]
    public function test_function_metadata_keeps_source_parameter_names(string $source, string $name, string $arglists, string $signature): void
    {
        $this->compilerFacade->compile($source, new CompileOptions());

        $meta = Phel::getDefinitionMetaData('user', $name);

        self::assertNotNull($meta);
        self::assertSame($arglists, $meta->find('arglists'));
        self::assertStringContainsString($signature, (string) $meta->find(Keyword::create('doc')));
    }

    public static function sourceSignatures(): iterable
    {
        yield 'munged collision' => [
            '(defn source-names-munged [a-b a_b] (+ a-b a_b))',
            'source-names-munged',
            '[a-b a_b]',
            '(source-names-munged a-b a_b)',
        ];
        yield 'reserved local' => [
            '(defn source-names-reserved [__phel_this this] [__phel_this this])',
            'source-names-reserved',
            '[__phel_this this]',
            '(source-names-reserved __phel_this this)',
        ];
        yield 'destructuring and variadic' => [
            '(defn source-names-destructured [a-b a_b [head & tail] & rest] [a-b a_b head tail rest])',
            'source-names-destructured',
            '[a-b a_b [head & tail] & rest]',
            '(source-names-destructured a-b a_b [head & tail] & rest)',
        ];
        yield 'renamed variadic binding' => [
            '(defn source-names-rest [a-b & a_b] a_b)',
            'source-names-rest',
            '[a-b & a_b]',
            '(source-names-rest a-b & a_b)',
        ];
        yield 'shadowed binding' => [
            '(defn source-names-shadowed [x x] x)',
            'source-names-shadowed',
            '[x x]',
            '(source-names-shadowed x x)',
        ];
        yield 'superglobal binding' => [
            '(defn source-names-superglobal [_GET] _GET)',
            'source-names-superglobal',
            '[_GET]',
            '(source-names-superglobal _GET)',
        ];
        yield 'multiple arities' => [
            '(defn source-names-multi ([a-b a_b] a-b) ([__phel_this this more] this))',
            'source-names-multi',
            '([a-b a_b] [__phel_this this more])',
            '(source-names-multi __phel_this this more)',
        ];
        yield 'macro implicit parameters' => [
            '(defmacro source-names-macro [a-b a_b] nil)',
            'source-names-macro',
            '[a-b a_b]',
            '(source-names-macro a-b a_b)',
        ];
    }

    public function test_defn_registers_fn_node_on_global_environment(): void
    {
        $this->compilerFacade->compile(
            '(defn identity-fn [x] x)',
            new CompileOptions(),
        );

        $node = self::$globalEnv->getDefFnNode('user', Symbol::create('identity-fn'));

        self::assertNotNull($node);
        self::assertCount(1, $node->getParams());
        self::assertSame('x', $node->getParams()[0]->getName());
    }

    public function test_def_with_literal_init_does_not_populate_side_table(): void
    {
        $this->compilerFacade->compile(
            '(def some-const 42)',
            new CompileOptions(),
        );

        self::assertNull(self::$globalEnv->getDefFnNode('user', Symbol::create('some-const')));
    }

    public function test_unknown_symbol_returns_null(): void
    {
        self::assertNull(self::$globalEnv->getDefFnNode('user', Symbol::create('never-defined')));
    }
}
