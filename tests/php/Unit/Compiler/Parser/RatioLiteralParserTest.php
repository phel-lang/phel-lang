<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser;

use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\ZeroDenominatorRatioParserException;
use Phel\Compiler\Domain\Parser\ExpressionParserFactory;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\TestCase;

final class RatioLiteralParserTest extends TestCase
{
    public function test_zero_denominator_reports_a_coded_error_at_the_literal(): void
    {
        $parser = new Parser(new ExpressionParserFactory(), new GlobalEnvironment());
        $tokenStream = new Lexer()->lexString("(+ 1\n   1/0)", 'ratio.phel');

        try {
            $parser->parseAll($tokenStream);
            self::fail('Expected an UnexpectedParserException');
        } catch (UnexpectedParserException $unexpectedParserException) {
            self::assertSame('Invalid ratio 1/0: the denominator is zero', $unexpectedParserException->getMessage());
            self::assertSame(ErrorCode::PARSER_ERROR, $unexpectedParserException->getErrorCode());
            self::assertSame(2, $unexpectedParserException->getStartLocation()->getLine());
            self::assertSame(3, $unexpectedParserException->getStartLocation()->getColumn());
            self::assertSame(6, $unexpectedParserException->getEndLocation()->getColumn());
            self::assertInstanceOf(ZeroDenominatorRatioParserException::class, $unexpectedParserException->getPrevious());
        }
    }
}
