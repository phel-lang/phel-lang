<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Source locations must survive every compiler phase
 * (lexer -> parser -> reader -> analyzer) so error reporting and source
 * maps can point back at the original Phel. This pins that contract
 * end-to-end for a representative form.
 */
final class SourceLocationPropagationTest extends TestCase
{
    protected function setUp(): void
    {
        CacheableConfig::reset();
        Gacela::bootstrap(__DIR__);
    }

    protected function tearDown(): void
    {
        CacheableConfig::reset();
    }

    public function test_top_level_form_keeps_its_start_location(): void
    {
        $node = $this->analyzeFirstForm('(php/+ 1 2)', 'top.phel');

        $loc = $node->getStartSourceLocation();
        self::assertInstanceOf(SourceLocation::class, $loc);
        self::assertSame('top.phel', $loc->getFile());
        self::assertSame(1, $loc->getLine());
        self::assertSame(0, $loc->getColumn());
    }

    public function test_line_is_propagated_for_a_form_below_blank_lines(): void
    {
        // Two leading newlines push the form to line 3.
        $node = $this->analyzeFirstForm("\n\n(php/+ 1 2)", 'below.phel');

        $loc = $node->getStartSourceLocation();
        self::assertInstanceOf(SourceLocation::class, $loc);
        self::assertSame(3, $loc->getLine());
    }

    private function analyzeFirstForm(string $code, string $file): AbstractNode
    {
        $facade = new CompilerFacade();
        $tokenStream = $facade->lexString($code, $file);

        while (($parseNode = $facade->parseNext($tokenStream)) instanceof NodeInterface) {
            if ($parseNode instanceof TriviaNodeInterface) {
                continue;
            }

            $ast = $facade->read($parseNode)->getAst();

            return $facade->analyze($ast, NodeEnvironment::empty());
        }

        self::fail('No analyzable form parsed from source');
    }
}
