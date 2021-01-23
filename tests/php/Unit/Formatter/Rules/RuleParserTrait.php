<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Lexer\Lexer;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Lang\Symbol;

trait RuleParserTrait
{
    private function parseStringToNode(string $string): NodeInterface
    {
        Symbol::resetGen();
        $parser = (new CompilerFactory())->createParser();
        $tokenStream = (new Lexer())->lexString($string);

        return $parser->parseAll($tokenStream);
    }
}
