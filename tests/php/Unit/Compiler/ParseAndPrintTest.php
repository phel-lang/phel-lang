<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Compiler\Lexer;
use Phel\Compiler\Parser;
use Phel\Compiler\ParserNode\NodeInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ParseAndPrintTest extends TestCase
{
    public function testParseAndPrintCoreLib()
    {
        $coreLibCode = file_get_contents(__DIR__ . '/../../../../src/phel/core.phel');

        $this->assertEquals(
            $coreLibCode,
            $this->printTrees($this->parse($coreLibCode))
        );
    }

    private function parse(string $string)
    {
        Symbol::resetGen();
        $parser = new Parser();
        $tokenStream = (new Lexer())->lexString($string);

        $parseTrees = [];
        while (true) {
            $parseTree = $parser->parseNext($tokenStream);
            if (!$parseTree) {
                break;
            }

            $parseTrees[] = $parseTree;
        }

        return $parseTrees;
    }

    /**
     * @param NodeInterface[] $parseTrees
     */
    private function printTrees(array $parseTrees): string
    {
        $code = '';
        /** @var NodeInterface $parseTree */
        foreach ($parseTrees as $parseTree)
        {
            $code .= $parseTree->getCode();
        }

        return $code;
    }
}
