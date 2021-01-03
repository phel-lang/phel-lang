<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\AnalyzeTable;
use Phel\Compiler\Ast\LiteralNode;
use Phel\Compiler\Ast\TableNode;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\NodeEnvironment;
use Phel\Lang\Table;
use PHPUnit\Framework\TestCase;

final class AnalyzeTableTest extends TestCase
{
    private AnalyzeTable $tableAnalyzer;

    public function setUp(): void
    {
        $this->tableAnalyzer = new AnalyzeTable(new Analyzer(new GlobalEnvironment()));
    }

    public function testEmptyTable(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new TableNode($env, [], null),
            $this->tableAnalyzer->analyze(Table::fromKVs(), $env)
        );
    }

    public function testTable(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new TableNode($env, [
                new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), 'a', null),
                new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), 1, null),
            ], null),
            $this->tableAnalyzer->analyze(Table::fromKVs('a', 1), $env)
        );
    }
}
