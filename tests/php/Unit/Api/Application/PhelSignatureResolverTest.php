<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhelSignatureResolver;
use Phel\Api\Domain\SymbolMetadataFinderInterface;
use Phel\Api\Transfer\PhelFunction;
use PHPUnit\Framework\TestCase;

use function strlen;

final class PhelSignatureResolverTest extends TestCase
{
    public function test_returns_signature_for_a_phel_call(): void
    {
        $help = $this->resolverFor($this->mapFn())->signatureAt(...$this->cursorAfter('(map '));

        self::assertNotNull($help);
        self::assertSame('(map f coll)', $help['signatures'][$help['activeSignature']]['label']);
        self::assertSame(
            [['label' => 'f'], ['label' => 'coll']],
            $help['signatures'][0]['parameters'],
        );
        self::assertSame(0, $help['activeParameter']);
    }

    public function test_exposes_every_arity_as_a_signature(): void
    {
        $help = $this->resolverFor($this->mapFn())->signatureAt(...$this->cursorAfter('(map '));

        self::assertNotNull($help);
        self::assertCount(2, $help['signatures']);
        self::assertSame('(map f coll & colls)', $help['signatures'][1]['label']);
    }

    public function test_active_parameter_tracks_the_argument_index(): void
    {
        $help = $this->resolverFor($this->mapFn())->signatureAt(...$this->cursorAfter('(map inc '));

        self::assertNotNull($help);
        self::assertSame(1, $help['activeParameter']);
    }

    public function test_selects_the_arity_that_covers_the_active_argument(): void
    {
        // Fourth argument (index 3): the fixed (map f coll) arity cannot hold it,
        // so the variadic arity is chosen and the index clamps to its last param.
        $help = $this->resolverFor($this->mapFn())->signatureAt(...$this->cursorAfter('(map f a b '));

        self::assertNotNull($help);
        self::assertSame(1, $help['activeSignature']);
        self::assertSame(2, $help['activeParameter']);
    }

    public function test_attaches_the_docstring_as_documentation(): void
    {
        $help = $this->resolverFor($this->mapFn())->signatureAt(...$this->cursorAfter('(map '));

        self::assertNotNull($help);
        self::assertSame('Maps f over coll.', $help['signatures'][0]['documentation'] ?? null);
    }

    public function test_keeps_a_destructured_parameter_whole(): void
    {
        $fn = new PhelFunction('user', 'handle', '', ['(handle [a b] c)'], '');

        $help = $this->resolverFor($fn)->signatureAt(...$this->cursorAfter('(handle '));

        self::assertNotNull($help);
        self::assertSame(
            [['label' => '[a b]'], ['label' => 'c']],
            $help['signatures'][0]['parameters'],
        );
    }

    public function test_counts_a_collection_literal_argument_as_one(): void
    {
        $fn = new PhelFunction('phel.core', 'reduce', '', ['(reduce f coll)'], '');

        // The vector is a single argument; the caret sits on the second arg.
        $help = $this->resolverFor($fn)->signatureAt(...$this->cursorAfter('(reduce [1 2 3] '));

        self::assertNotNull($help);
        self::assertSame(1, $help['activeParameter']);
    }

    public function test_returns_null_when_not_inside_a_call(): void
    {
        self::assertNull($this->resolverFor($this->mapFn())->signatureAt(...$this->cursorAfter('map ')));
    }

    public function test_returns_null_for_a_php_interop_head(): void
    {
        // php/... calls are the PhpInteropDocResolver's job; the Phel resolver
        // must defer so it does not shadow interop signature help.
        self::assertNull($this->resolverFor($this->mapFn())->signatureAt(...$this->cursorAfter('(php/new ')));
    }

    public function test_returns_null_when_the_symbol_is_unknown(): void
    {
        self::assertNull($this->resolverFor(null)->signatureAt(...$this->cursorAfter('(unknown ')));
    }

    public function test_returns_null_when_the_function_has_no_signatures(): void
    {
        $fn = new PhelFunction('user', 'x', '', [], '');

        self::assertNull($this->resolverFor($fn)->signatureAt(...$this->cursorAfter('(x ')));
    }

    private function resolverFor(?PhelFunction $fn): PhelSignatureResolver
    {
        $finder = $this->createStub(SymbolMetadataFinderInterface::class);
        $finder->method('find')->willReturn($fn);

        return new PhelSignatureResolver($finder);
    }

    private function mapFn(): PhelFunction
    {
        return new PhelFunction(
            'phel.core',
            'map',
            'Maps f over coll.',
            ['(map f coll)', '(map f coll & colls)'],
            '',
        );
    }

    /**
     * Place the caret at the end of $source (line 1), mirroring how an editor
     * requests signature help while the argument list is still open.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private function cursorAfter(string $source): array
    {
        return [$source, 1, strlen($source) + 1];
    }
}
