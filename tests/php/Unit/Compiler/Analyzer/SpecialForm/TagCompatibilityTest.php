<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\TagCompatibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagCompatibilityTest extends TestCase
{
    #[DataProvider('providerAccepts')]
    public function test_accepts(string $tag, string $literalType, bool $expected): void
    {
        self::assertSame($expected, TagCompatibility::accepts($tag, $literalType));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerAccepts(): iterable
    {
        yield 'mixed accepts a scalar' => ['mixed', 'int', true];
        yield 'mixed accepts nil' => ['mixed', 'nil', true];
        yield 'empty tag accepts anything' => ['', 'string', true];

        yield 'nullable accepts nil' => ['?int', 'nil', true];
        yield 'nullable accepts the inner type' => ['?int', 'int', true];
        yield 'nullable rejects an unrelated type' => ['?int', 'string', false];

        yield 'union accepts a member' => ['int|string', 'string', true];
        yield 'union rejects a non-member' => ['int|string', 'bool', false];
        yield 'union with null accepts nil' => ['int|null', 'nil', true];

        yield 'never rejects a concrete return' => ['never', 'int', false];
        yield 'never rejects nil' => ['never', 'nil', false];
        yield 'void rejects a concrete return' => ['void', 'string', false];
        yield 'void rejects nil' => ['void', 'nil', false];

        yield 'null tag accepts nil' => ['null', 'nil', true];
        yield 'null tag rejects a value' => ['null', 'int', false];

        yield 'int accepts int' => ['int', 'int', true];
        yield 'int rejects string' => ['int', 'string', false];
        yield 'float accepts int' => ['float', 'int', true];

        yield 'unknown class tag is not rejected' => ['\DateTime', 'vector', true];
    }
}
