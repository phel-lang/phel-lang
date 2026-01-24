<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser;

use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ExpressionParserFactory;
use PHPUnit\Framework\TestCase;

final class ListParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $env = new GlobalEnvironment();
        $this->parser = new Parser(new ExpressionParserFactory(), $env);
    }

    public function test_unterminated_list_error_throws_unfinished_exception(): void
    {
        $code = '(def foo
  (fn [x]
    (+ x 1)
';

        $this->expectException(UnfinishedParserException::class);
        $this->expectExceptionMessageMatches('/Unterminated list/');

        $lexer = new Lexer();
        $tokenStream = $lexer->lexString($code, 'test.phel');
        $this->parser->parseAll($tokenStream);
    }

    public function test_unterminated_vector_error_throws_unfinished_exception(): void
    {
        $code = '[1 2 3';

        $this->expectException(UnfinishedParserException::class);
        $this->expectExceptionMessageMatches('/Unterminated list/');

        $lexer = new Lexer();
        $tokenStream = $lexer->lexString($code, 'test.phel');
        $this->parser->parseAll($tokenStream);
    }

    public function test_unterminated_map_error_throws_unfinished_exception(): void
    {
        $code = '{:a 1 :b 2';

        $this->expectException(UnfinishedParserException::class);
        $this->expectExceptionMessageMatches('/Unterminated list/');

        $lexer = new Lexer();
        $tokenStream = $lexer->lexString($code, 'test.phel');
        $this->parser->parseAll($tokenStream);
    }
}
