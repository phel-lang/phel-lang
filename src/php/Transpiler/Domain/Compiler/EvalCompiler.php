<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Compiler;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Transpiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Transpiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Exceptions\CompilerException;
use Phel\Transpiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Transpiler\Domain\Lexer\LexerInterface;
use Phel\Transpiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Transpiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Transpiler\Domain\Parser\ParserInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Transpiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Transpiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Transpiler\Domain\Reader\ReaderInterface;
use Phel\Transpiler\Infrastructure\CompileOptions;

final readonly class EvalCompiler implements EvalCompilerInterface
{
    public function __construct(
        private LexerInterface $lexer,
        private ParserInterface $parser,
        private ReaderInterface $reader,
        private AnalyzerInterface $analyzer,
        private StatementEmitterInterface $emitter,
        private EvaluatorInterface $evaluator,
    ) {
    }

    /**
     * Evaluates a provided Phel code.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws CompilerException
     * @throws FileException
     * @throws LexerValueException
     * @throws UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalString(string $phelCode, CompileOptions $compileOptions): mixed
    {
        $tokenStream = $this->lexer->lexString($phelCode, $compileOptions->getSource(), $compileOptions->getStartingLine());

        $result = null;
        while (true) {
            try {
                $parseTree = $this->parser->parseNext($tokenStream);

                if (!$parseTree instanceof NodeInterface) {
                    return $result;
                }

                if (!$parseTree instanceof TriviaNodeInterface) {
                    $readerResult = $this->reader->read($parseTree);
                    $node = $this->analyze($readerResult);

                    $result = $this->evalNode($node, $compileOptions);
                }
            } catch (UnfinishedParserException $e) {
                throw $e;
            } catch (AbstractParserException|ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }
    }

    public function evalForm(float|bool|int|string|TypeInterface|null $form, CompileOptions $compileOptions): mixed
    {
        $node = $this->analyzer->analyze($form, NodeEnvironment::empty()->withReturnContext());
        return $this->evalNode($node, $compileOptions);
    }

    /**
     * @throws CompilerException
     */
    private function analyze(ReaderResult $readerResult): AbstractNode
    {
        try {
            return $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()->withReturnContext(),
            );
        } catch (AnalyzerException $analyzerException) {
            throw new CompilerException($analyzerException, $readerResult->getCodeSnippet());
        }
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return mixed The result of the executed code
     */
    private function evalNode(AbstractNode $node, CompileOptions $compileOptions): mixed
    {
        $code = $this->emitter->emitNode($node, $compileOptions->isSourceMapsEnabled())->getCodeWithSourceMap();

        return $this->evaluator->eval($code);
    }
}
