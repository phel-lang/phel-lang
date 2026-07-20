<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\CompletionDocFormatter;
use Phel\Api\Application\CompletionDocResolver;
use Phel\Api\Domain\SymbolMetadataFinderInterface;
use Phel\Shared\Api\PhelFunction;
use PHPUnit\Framework\TestCase;

final class CompletionDocResolverTest extends TestCase
{
    public function test_resolves_signature_and_summary_for_known_symbol(): void
    {
        $finder = $this->createStub(SymbolMetadataFinderInterface::class);
        $finder->method('find')->willReturn(PhelFunction::fromArray([
            'name' => 'map',
            'signatures' => ['(map f coll)'],
            'desc' => 'Maps over a collection.',
        ]));

        $resolver = new CompletionDocResolver($finder, new CompletionDocFormatter());

        self::assertSame('(map f coll): Maps over a collection.', $resolver->resolve('map'));
    }

    public function test_skips_php_interop_candidates(): void
    {
        $finder = $this->createMock(SymbolMetadataFinderInterface::class);
        $finder->expects(self::never())->method('find');

        $resolver = new CompletionDocResolver($finder, new CompletionDocFormatter());

        self::assertNull($resolver->resolve('php/strlen'));
        self::assertNull($resolver->resolve(''));
    }

    public function test_null_when_symbol_has_no_metadata(): void
    {
        $finder = $this->createStub(SymbolMetadataFinderInterface::class);
        $finder->method('find')->willReturn(null);

        $resolver = new CompletionDocResolver($finder, new CompletionDocFormatter());

        self::assertNull($resolver->resolve('unknown-symbol'));
    }
}
