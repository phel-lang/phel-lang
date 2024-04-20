<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\TranspilerFactory;
use PHPUnit\Framework\TestCase;

final class ParseAndPrintTest extends TestCase
{
    private TranspilerFactory $transpilerFactory;

    protected function setUp(): void
    {
        $this->transpilerFactory = new TranspilerFactory();
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
        $parser = $this->transpilerFactory->createParser();
        $tokenStream = $this->transpilerFactory->createLexer()->lexString($phelCode);

        $parseTrees = [];
        while (true) {
            $parseTree = $parser->parseNext($tokenStream);
            if (!$parseTree instanceof NodeInterface) {
                break;
            }

            $parseTrees[] = $parseTree;
        }

        return $parseTrees;
    }

    /**
     * @param list<NodeInterface> $parseTrees
     */
    private function printTrees(array $parseTrees): string
    {
        return implode('', array_map(
            static fn (NodeInterface $t): string => $t->getCode(),
            $parseTrees,
        ));
    }
}
