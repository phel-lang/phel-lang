<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Application;

use Phel\Compiler\Application\EvalCompiler;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Compiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Compiler\Infrastructure\CompileOptions;
use PHPUnit\Framework\TestCase;

final class EvalCompilerTest extends TestCase
{
    public function test_eval_form_passes_compile_options_to_evaluator(): void
    {
        $compileOptions = (new CompileOptions())
            ->setSource('tests/php/example-test.phel')
            ->setIsEnabledSourceMaps(false);

        $lexer = $this->createStub(LexerInterface::class);
        $parser = $this->createStub(ParserInterface::class);
        $reader = $this->createStub(ReaderInterface::class);

        $analyzer = $this->createMock(AnalyzerInterface::class);
        $emitter = $this->createMock(StatementEmitterInterface::class);
        $evaluator = $this->createMock(EvaluatorInterface::class);

        $node = new class(NodeEnvironment::empty()->withReturnContext()) extends AbstractNode {
        };

        $analyzer->expects(self::once())
            ->method('analyze')
            ->willReturn($node);

        $emitter->expects(self::once())
            ->method('emitNode')
            ->with($node, false)
            ->willReturn(new EmitterResult(false, 'php code', '', 'source.php'));

        $evaluator->expects(self::once())
            ->method('eval')
            ->with('php code', self::identicalTo($compileOptions))
            ->willReturn('result');

        $compiler = new EvalCompiler($lexer, $parser, $reader, $analyzer, $emitter, $evaluator);

        $result = $compiler->evalForm('form', $compileOptions);

        self::assertSame('result', $result);
    }
}
