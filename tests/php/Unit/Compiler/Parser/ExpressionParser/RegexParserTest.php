<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\RegexParserException;
use Phel\Compiler\Domain\Parser\ExpressionParser\RegexParser;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

final class RegexParserTest extends TestCase
{
    public function test_parse_delimits_the_pattern(): void
    {
        self::assertSame('/a\/b"c/', $this->parse('#"a/b\"c"')->getValue());
    }

    #[DataProvider('slashBackslashRuns')]
    public function test_parse_slash_after_backslashes(int $count): void
    {
        $backslashes = str_repeat('\\', $count);
        $pattern = $this->parse('#"' . $backslashes . '/"')->getValue();

        self::assertSame('/' . $backslashes . ($count % 2 === 0 ? '\\' : '') . '//', $pattern);
        self::assertMatchesRegularExpression($pattern, str_repeat('\\', intdiv($count, 2)) . '/');
        self::assertDoesNotMatchRegularExpression($pattern, 'no match');
    }

    public static function slashBackslashRuns(): iterable
    {
        yield 'zero' => [0];
        yield 'one' => [1];
        yield 'two' => [2];
        yield 'three' => [3];
    }

    public function test_parse_rejects_a_pattern_that_does_not_compile(): void
    {
        $this->expectException(RegexParserException::class);
        $this->expectExceptionMessage('Invalid regex #"[a-": missing terminating ] for character class at offset 3');

        $this->parse('#"[a-"');
    }

    private function parse(string $code): StringNode
    {
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, strlen($code));

        return new RegexParser()->parse(new Token(Token::T_REGEX, $code, $start, $end));
    }
}
