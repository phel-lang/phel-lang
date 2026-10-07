<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Parser;

use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ExpressionParserFactory;
use PhelTest\Support\RendersExceptionReportTrait;
use PHPUnit\Framework\TestCase;

/**
 * Pins what a regex or number literal that cannot mean anything prints. Both
 * used to compile: the regex failed later with a PHP warning, and `08` read as 8.
 */
final class InvalidLiteralReportTest extends TestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'broken.phel';

    public function test_a_regex_that_does_not_compile_names_the_problem(): void
    {
        $expected = <<<'REPORT'
            [PHEL120] Invalid regex #"[a-": missing terminating ] for character class at offset 3
            in broken.phel:1

            1| (def r #"[a-")
                      ^^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(def r #"[a-")'));
    }

    public function test_a_leading_zero_before_8_or_9_is_an_invalid_number(): void
    {
        $expected = <<<'REPORT'
            [PHEL120] Invalid number: 08
            in broken.phel:1

            1| (inc 08)
                    ^^

            REPORT;

        self::assertSame($expected, $this->report('(inc 08)'));
    }

    private function report(string $phelCode): string
    {
        $parser = new Parser(new ExpressionParserFactory(), new GlobalEnvironment());

        try {
            $parser->parseAll(new Lexer()->lexString($phelCode, self::SOURCE));
        } catch (AbstractParserException $parserException) {
            return $this->exceptionReport($parserException, $parserException->getCodeSnippet());
        }

        self::fail('Expected the parser to reject: ' . $phelCode);
    }
}
