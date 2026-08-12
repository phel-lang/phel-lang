<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Generator;
use Phel\Api\Application\SymbolExtractor;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Shared\Api\Definition;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;

final class SymbolExtractorTest extends TestCase
{
    public function test_it_marks_defn_dash_form_as_private(): void
    {
        $extractor = $this->extractor();
        $form = $this->list([
            Symbol::create('defn-'),
            Symbol::create('secret'),
            $this->vector([Symbol::create('x')]),
        ]);

        $definition = $extractor->definitionFromForm($form, 'user', 'x.phel');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertTrue($definition->private);
        self::assertSame('secret', $definition->name);
        self::assertSame(Definition::KIND_DEFN, $definition->kind);
    }

    public function test_it_marks_plain_defn_form_as_public(): void
    {
        $extractor = $this->extractor();
        $form = $this->list([
            Symbol::create('defn'),
            Symbol::create('public-fn'),
            $this->vector([Symbol::create('x')]),
        ]);

        $definition = $extractor->definitionFromForm($form, 'user', 'x.phel');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertFalse($definition->private);
    }

    public function test_it_marks_form_with_private_metadata_as_private(): void
    {
        $extractor = $this->extractor();
        $meta = TypeFactory::getInstance()->persistentMapFromKVs(
            Keyword::create('private'),
            true,
        );
        $form = $this->list([
            Symbol::create('defn'),
            Symbol::create('hidden'),
            $meta,
            $this->vector([Symbol::create('x')]),
        ]);

        $definition = $extractor->definitionFromForm($form, 'user', 'x.phel');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertTrue($definition->private);
    }

    public function test_it_extracts_namespace_declaration_location(): void
    {
        $namespace = Symbol::create('phel.pprint');
        $namespace->setStartLocation(new SourceLocation('pprint.phel', 3, 5));
        $namespace->setEndLocation(new SourceLocation('pprint.phel', 3, 16));

        $form = $this->list([Symbol::create('ns'), $namespace]);

        $facade = $this->createStub(CompilerFacadeInterface::class);
        $facade->method('readFormsBestEffort')->willReturn((static function () use ($form): Generator {
            yield $form;
        })());

        $result = new SymbolExtractor($facade)->extract('(ns phel.pprint)', 'pprint.phel');

        self::assertSame('phel.pprint', $result['namespace']);
        self::assertNotNull($result['namespaceLocation']);
        self::assertSame('pprint.phel', $result['namespaceLocation']->uri);
        self::assertSame(3, $result['namespaceLocation']->line);
        self::assertSame(5, $result['namespaceLocation']->col);
        self::assertSame(16, $result['namespaceLocation']->endCol);
    }

    public function test_it_extracts_signature_and_namespace(): void
    {
        $extractor = $this->extractor();
        $form = $this->list([
            Symbol::create('defn'),
            Symbol::create('add'),
            $this->vector([Symbol::create('a'), Symbol::create('b')]),
        ]);

        $definition = $extractor->definitionFromForm($form, 'user\\math', 'math.phel');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertSame('user\\math', $definition->namespace);
        self::assertSame(['[a b]'], $definition->signature);
    }

    public function test_it_uses_name_start_location_for_line_and_column(): void
    {
        $extractor = $this->extractor();
        $name = Symbol::create('located');
        $name->setStartLocation(new SourceLocation('x.phel', 7, 3));

        $form = $this->list([
            Symbol::create('defn'),
            $name,
            $this->vector([]),
        ]);

        $definition = $extractor->definitionFromForm($form, 'user', 'x.phel');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertSame(7, $definition->line);
        self::assertSame(3, $definition->col);
    }

    public function test_it_records_the_deprecation_reason_from_the_metadata_map(): void
    {
        $extractor = $this->extractor();
        $meta = TypeFactory::getInstance()->persistentMapFromKVs(
            Keyword::create('deprecated'),
            'use with-meta',
        );
        $form = $this->list([
            Symbol::create('defn'),
            Symbol::create('set-meta!'),
            $meta,
            $this->vector([Symbol::create('x')]),
        ]);

        $definition = $extractor->definitionFromForm($form, 'user', 'x.phel');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertTrue($definition->isDeprecated());
        self::assertSame('use with-meta', $definition->deprecated);
    }

    public function test_it_reads_a_bare_deprecated_flag_from_the_name_metadata(): void
    {
        $extractor = $this->extractor();
        $name = Symbol::create('old')->withMeta(
            TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('deprecated'), true),
        );
        $form = $this->list([
            Symbol::create('defn'),
            $name,
            $this->vector([]),
        ]);

        $definition = $extractor->definitionFromForm($form, 'user', 'x.phel');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertSame('deprecated', $definition->deprecated);
    }

    public function test_a_docstring_mentioning_deprecated_does_not_deprecate_the_definition(): void
    {
        $extractor = $this->extractor();
        $form = $this->list([
            Symbol::create('defn'),
            Symbol::create('symbol-info'),
            'Returns keys :doc and :deprecated for a symbol.',
            $this->vector([]),
        ]);

        $definition = $extractor->definitionFromForm($form, 'user', 'x.phel');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertFalse($definition->isDeprecated());
    }

    public function test_it_returns_null_for_non_definition_form(): void
    {
        $extractor = $this->extractor();
        $form = $this->list([
            Symbol::create('not-a-def'),
            Symbol::create('foo'),
        ]);

        self::assertNull($extractor->definitionFromForm($form, 'user', 'x.phel'));
    }

    private function extractor(): SymbolExtractor
    {
        // definitionFromForm operates on already-read forms and never touches the facade.
        return new SymbolExtractor($this->createStub(CompilerFacadeInterface::class));
    }

    /**
     * @param list<mixed> $values
     */
    private function list(array $values): mixed
    {
        return TypeFactory::getInstance()->persistentListFromArray($values);
    }

    /**
     * @param list<mixed> $values
     */
    private function vector(array $values): mixed
    {
        return TypeFactory::getInstance()->persistentVectorFromArray($values);
    }
}
