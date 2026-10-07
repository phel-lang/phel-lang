<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Reader;

use Phel;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ExpressionReader\DuplicateKeyGuard;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\SymbolNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;

final class DuplicateKeyGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{list<mixed>, string}>
     */
    public static function providerRepeatedKey(): iterable
    {
        yield 'symbol' => [[Symbol::create('a'), Symbol::create('a')], 'Duplicate key: a'];
        yield 'qualified symbol' => [[Symbol::create('x/a'), Symbol::create('x/a')], 'Duplicate key: x/a'];
        yield 'vector' => [[Phel::vector([1, 2]), Phel::vector([1, 2])], 'Duplicate key: [1 2]'];
        yield 'vector of symbols' => [[Phel::vector([Symbol::create('a')]), Phel::vector([Symbol::create('a')])], 'Duplicate key: [a]'];
        yield 'map' => [[Phel::map(Keyword::create('a'), 1), Phel::map(Keyword::create('a'), 1)], 'Duplicate key: {:a 1}'];
        yield 'set' => [[Phel::set([Symbol::create('a')]), Phel::set([Symbol::create('a')])], 'Duplicate key: #{a}'];
    }

    /**
     * @param list<mixed> $keys
     */
    #[DataProvider('providerRepeatedKey')]
    public function test_a_repeated_key_is_rejected(array $keys, string $message): void
    {
        try {
            DuplicateKeyGuard::assertUnique($keys, $this->nodes(count($keys)), 1, $this->node());
        } catch (ReaderException $readerException) {
            self::assertSame($message, $readerException->getMessage());
            self::assertSame(ErrorCode::DUPLICATE_KEY, $readerException->getErrorCode());

            return;
        }

        self::fail('Expected a duplicate key error');
    }

    /**
     * @return iterable<string, array{list<mixed>}>
     */
    public static function providerRepeatedCall(): iterable
    {
        yield 'call' => [[Phel::list([Symbol::create('f')]), Phel::list([Symbol::create('f')])]];
        yield 'vector holding a call' => [[Phel::vector([Phel::list([Symbol::create('f')])]), Phel::vector([Phel::list([Symbol::create('f')])])]];
    }

    /**
     * @param list<mixed> $keys
     */
    #[DataProvider('providerRepeatedCall')]
    public function test_a_repeated_call_keeps_the_last_value(array $keys): void
    {
        DuplicateKeyGuard::assertUnique($keys, $this->nodes(count($keys)), 1, $this->node());

        $this->addToAssertionCount(1);
    }

    /**
     * @return list<SymbolNode>
     */
    private function nodes(int $count): array
    {
        return array_map($this->node(...), range(1, $count));
    }

    private function node(): SymbolNode
    {
        $location = new SourceLocation('test.phel', 1, 0);

        return new SymbolNode('a', $location, $location, Symbol::create('a'));
    }
}
