<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use DateTimeImmutable;
use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\NodeInterface;
use PHPUnit\Framework\TestCase;

final class CompilerFacadeAnalyzeTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }

    public function test_it_analyzes_an_inst_literal_as_read(): void
    {
        $compiler = new CompilerFacade();
        $parseTree = $compiler->parseNext($compiler->lexString('#inst "2026-01-01T00:00:00Z"', 'inst.phel'));
        self::assertInstanceOf(NodeInterface::class, $parseTree);

        $form = $compiler->read($parseTree)->getAst();
        self::assertInstanceOf(DateTimeImmutable::class, $form);

        $node = $compiler->analyze($form, $compiler->emptyNodeEnvironment());

        self::assertInstanceOf(LiteralNode::class, $node);
        self::assertSame($form, $node->getValue());
    }
}
