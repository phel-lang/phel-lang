<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\SymbolMetadataFinder;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Transfer\PhelFunction;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\TypeFactory;
use Phel\Shared\Munge;
use PHPUnit\Framework\TestCase;

final class SymbolMetadataFinderTest extends TestCase
{
    private SymbolMetadataFinder $finder;

    public static function tearDownAfterClass(): void
    {
        Registry::getInstance()->clear();
    }

    protected function setUp(): void
    {
        Registry::getInstance()->clear();
        $catalog = $this->createStub(PhelFnNormalizerInterface::class);
        $catalog->method('getPhelFunctions')->willReturn([]);
        $this->finder = new SymbolMetadataFinder(new Munge(), $catalog);
    }

    public function test_it_returns_null_for_empty_symbol(): void
    {
        self::assertNull($this->finder->find(''));
    }

    public function test_it_returns_null_for_unknown_symbol(): void
    {
        self::assertNull($this->finder->find('does-not-exist'));
    }

    public function test_it_resolves_bare_symbol_in_current_namespace(): void
    {
        $this->register('user', 'greet', [
            'doc' => 'Says hi',
            'arglists' => '(greet name)',
        ], file: '/tmp/user.phel', line: 7);

        $fn = $this->finder->find('greet', 'user');

        self::assertNotNull($fn);
        self::assertSame('user', $fn->namespace);
        self::assertSame('greet', $fn->name);
        self::assertSame('Says hi', $fn->doc);
        self::assertSame(['(greet name)'], $fn->signatures);
        self::assertSame('/tmp/user.phel', $fn->file);
        self::assertSame(7, $fn->line);
    }

    public function test_it_falls_back_to_phel_core_for_bare_symbols(): void
    {
        $this->register('phel.core', 'map', [
            'doc' => 'Maps',
            'arglists' => '(map f & colls)',
        ]);

        $fn = $this->finder->find('map', 'user');

        self::assertNotNull($fn);
        self::assertSame('phel.core', $fn->namespace);
        self::assertSame(['(map f & colls)'], $fn->signatures);
    }

    public function test_it_resolves_qualified_symbol_with_dot_separator(): void
    {
        $this->register('phel.string', 'upper-case', [
            'doc' => 'Uppercases',
            'arglists' => '(upper-case s)',
        ]);

        $fn = $this->finder->find('phel.string/upper-case');

        self::assertNotNull($fn);
        self::assertSame('phel.string', $fn->namespace);
        self::assertSame('upper-case', $fn->name);
    }

    public function test_it_resolves_qualified_symbol_with_backslash_separator(): void
    {
        $this->register('phel.string', 'upper-case', [
            'doc' => 'Uppercases',
            'arglists' => '(upper-case s)',
        ]);

        $fn = $this->finder->find('phel\\string/upper-case');

        self::assertNotNull($fn);
        self::assertSame('phel.string', $fn->namespace);
        self::assertSame('upper-case', $fn->name);
    }

    public function test_it_handles_multi_arity_arglists(): void
    {
        $this->register('user', 'multi', [
            'doc' => '',
            'arglists' => "(multi)\n(multi x)\n(multi x y)",
        ]);

        $fn = $this->finder->find('multi', 'user');

        self::assertNotNull($fn);
        self::assertSame(['(multi)', '(multi x)', '(multi x y)'], $fn->signatures);
    }

    public function test_it_falls_back_to_docstring_signatures_when_arglists_missing(): void
    {
        $this->register('user', 'doc-only', [
            'doc' => "```phel\n(doc-only x)\n```\nDescribes.",
        ]);

        $fn = $this->finder->find('doc-only', 'user');

        self::assertNotNull($fn);
        self::assertSame(['(doc-only x)'], $fn->signatures);
    }

    public function test_it_scans_all_namespaces_as_last_resort(): void
    {
        $this->register('totally.elsewhere', 'unique-name', [
            'doc' => '',
            'arglists' => '(unique-name)',
        ]);

        $fn = $this->finder->find('unique-name', 'user');

        self::assertNotNull($fn);
        self::assertSame('totally.elsewhere', $fn->namespace);
    }

    public function test_it_falls_back_to_static_catalog_for_native_special_forms(): void
    {
        $native = new PhelFunction(
            namespace: 'core',
            name: 'def',
            doc: 'Binds a value',
            signatures: ['(def name meta? value)'],
            description: '',
        );

        $catalog = $this->createStub(PhelFnNormalizerInterface::class);
        $catalog->method('getPhelFunctions')->willReturn([$native]);

        $finder = new SymbolMetadataFinder(new Munge(), $catalog);

        $fn = $finder->find('def', 'user');

        self::assertNotNull($fn);
        self::assertSame('def', $fn->name);
        self::assertSame('core', $fn->namespace);
    }

    /**
     * @param array<string, mixed> $metaPairs
     */
    private function register(
        string $ns,
        string $name,
        array $metaPairs,
        ?string $file = null,
        ?int $line = null,
    ): void {
        $munge = new Munge();
        $encodedNs = $munge->encodeRegistryKey($ns);
        $encodedName = $munge->encode($name);

        $kvs = [];
        foreach ($metaPairs as $key => $value) {
            $kvs[] = $key === 'arglists' ? 'arglists' : Keyword::create($key);
            $kvs[] = $value;
        }

        if ($file !== null || $line !== null) {
            $location = TypeFactory::getInstance()->persistentMapFromKVs(
                Keyword::create('file'),
                $file ?? '',
                Keyword::create('line'),
                $line ?? 0,
            );
            $kvs[] = Keyword::create('start-location');
            $kvs[] = $location;
        }

        $meta = TypeFactory::getInstance()->persistentMapFromKVs(...$kvs);
        Registry::getInstance()->addDefinition($encodedNs, $encodedName, null, $meta);
    }
}
