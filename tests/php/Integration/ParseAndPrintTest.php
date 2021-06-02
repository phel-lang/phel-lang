<?php

declare(strict_types=1);

namespace PhelTest\Integration;

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

    public function test_parse_and_print_core_library(): void
    {
        $coreLibCode = file_get_contents(__DIR__ . '/../../../src/phel/core.phel');

        $parsedTrees = $this->parse($coreLibCode);
        $printedTrees = $this->printTrees($parsedTrees);

        self::assertEquals($coreLibCode, $printedTrees);
    }

    private function parse(string $phelCode): array
    {
        Symbol::resetGen();
        $parser = $this->compilerFactory->createParser();
        $tokenStream = $this->compilerFactory->createLexer()->lexString($phelCode);

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
        return implode(array_map(
            static fn (NodeInterface $t) => $t->getCode(),
            $parseTrees
        ));
    }
}
