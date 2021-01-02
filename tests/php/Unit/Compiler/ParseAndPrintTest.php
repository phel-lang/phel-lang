<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ParseAndPrintTest extends TestCase
{
    private CompilerFactory $compilerFactory;

    public function setUp(): void
    {
        $this->compilerFactory = new CompilerFactory();
    }

    public function testParseAndPrintCoreLib(): void
    {
        $coreLibCode = file_get_contents(__DIR__ . '/../../../../src/phel/core.phel');

        self::assertEquals(
            $coreLibCode,
            $this->printTrees($this->parse($coreLibCode))
        );
    }

    private function parse(string $string): array
    {
        Symbol::resetGen();
        $parser = $this->compilerFactory->createParser();
        $tokenStream = $this->compilerFactory->createLexer()->lexString($string);

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
        foreach ($parseTrees as $parseTree) {
            $code .= $parseTree->getCode();
        }

        return $code;
    }
}
