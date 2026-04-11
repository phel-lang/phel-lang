<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Init;

use Phel\Run\Domain\Init\NamespaceNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NamespaceNormalizerTest extends TestCase
{
    private NamespaceNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new NamespaceNormalizer();
    }

    #[DataProvider('namespaceProvider')]
    public function test_normalize(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function namespaceProvider(): iterable
    {
        yield 'simple name' => ['app', 'app\\main'];
        yield 'with hyphens' => ['my-app', 'myapp\\main'];
        yield 'with underscores' => ['my_app', 'myapp\\main'];
        yield 'with multiple hyphens' => ['my-cool-app', 'mycoolapp\\main'];
        yield 'mixed separators' => ['my-cool_app', 'mycoolapp\\main'];
        yield 'uppercase' => ['MyApp', 'myapp\\main'];
        yield 'mixed case with hyphens' => ['My-Cool-App', 'mycoolapp\\main'];
        yield 'with numbers' => ['app123', 'app123\\main'];
        yield 'with special chars' => ['my.app!', 'myapp\\main'];
        yield 'empty becomes just main' => ['', '\\main'];
    }
}
