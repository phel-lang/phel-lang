<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Exceptions;

use Exception;
use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AnalyzerExceptionTest extends TestCase
{
    public function test_macro_expansion_error_includes_definition_location(): void
    {
        $node = $this->globalVarNode(Phel::map(
            Keyword::create('macro'),
            true,
            Keyword::create('start-location'),
            Phel::map(
                Keyword::create('file'),
                '/proj/src/macros.phel',
                Keyword::create('line'),
                12,
                Keyword::create('column'),
                0,
            ),
        ));

        $exception = $this->expandMacro($node);

        self::assertStringContainsString('Error in expanding macro "user\my-macro"', $exception->getMessage());
        self::assertStringContainsString('Defined: /proj/src/macros.phel:12', $exception->getMessage());
    }

    public function test_macro_expansion_error_omits_definition_location_when_meta_absent(): void
    {
        $node = $this->globalVarNode(Phel::map(Keyword::create('macro'), true));

        $exception = $this->expandMacro($node);

        self::assertStringNotContainsString('Defined:', $exception->getMessage());
    }

    private function expandMacro(GlobalVarNode $node): AnalyzerException
    {
        try {
            AnalyzerException::whenExpandingMacro(
                Phel::list([Symbol::createForNamespace('user', 'my-macro')]),
                $node,
                new RuntimeException('macro exploded'),
            );
            self::fail('Expected AnalyzerException');
        } catch (AnalyzerException $analyzerException) {
            return $analyzerException;
        } catch (Exception $exception) {
            self::fail('Unexpected exception: ' . $exception->getMessage());
        }
    }

    private function globalVarNode(mixed $meta): GlobalVarNode
    {
        return new GlobalVarNode(
            NodeEnvironment::empty(),
            'user',
            Symbol::create('my-macro'),
            $meta,
        );
    }
}
