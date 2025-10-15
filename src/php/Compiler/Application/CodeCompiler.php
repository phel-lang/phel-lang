<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Compiler\CodeCompilerInterface;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Emitter\FileEmitterInterface;
use Phel\Compiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Compiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Compiler\Infrastructure\CompilationCacheManager;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Lang\TypeInterface;
use Phel\Shared\BuildConstants;
use Phel\Shared\CompilerConstants;
use Throwable;

final readonly class CodeCompiler implements CodeCompilerInterface
{
    public function __construct(
        private LexerInterface $lexer,
        private ParserInterface $parser,
        private ReaderInterface $reader,
        private AnalyzerInterface $analyzer,
        private StatementEmitterInterface $statementEmitter,
        private FileEmitterInterface $fileEmitter,
        private EvaluatorInterface $evaluator,
        private CompilationCacheManager $cacheManager,
    ) {
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws LexerValueException
     */
    public function compileString(string $phelCode, CompileOptions $compileOptions): EmitterResult
    {
        $sourceFile = $compileOptions->getSource();

        // Check if we have a valid cached version
        if ($this->shouldUseCachedVersion($sourceFile)) {
            $cachedPhpPath = $this->cacheManager->getCachedPhpPath($sourceFile);
            if ($cachedPhpPath !== null) {
                return $this->loadFromCache($cachedPhpPath, $sourceFile, $compileOptions);
            }
        }

        // No cache or cache invalid - proceed with compilation
        $tokenStream = $this->lexer->lexString(
            $phelCode,
            $sourceFile,
            $compileOptions->getStartingLine(),
        );

        $this->fileEmitter->startFile($sourceFile);
        while (true) {
            try {
                $parseTree = $this->parser->parseNext($tokenStream);
                // If we reached the end exit this loop
                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if (!$parseTree instanceof TriviaNodeInterface) {
                    $readerResult = $this->reader->read($parseTree);
                    $node = $this->analyze($readerResult);
                    // Still evaluate per-form for macro support, but accumulate for caching
                    $this->emitNode($node, $compileOptions);
                }
            } catch (AbstractParserException|ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }

        $result = $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());

        // Cache the result (forms were already evaluated per-form for macro support)
        $this->cacheResult($result, $compileOptions);

        return $result;
    }

    public function compileForm(
        float|bool|int|string|TypeInterface|null $form,
        CompileOptions $compileOptions,
    ): EmitterResult {
        $this->fileEmitter->startFile($compileOptions->getSource());
        $node = $this->analyzer->analyze($form, NodeEnvironment::empty());
        $this->emitNode($node, $compileOptions);
        return $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());
    }

    /**
     * @throws CompilerException
     */
    private function analyze(ReaderResult $readerResult): AbstractNode
    {
        try {
            return $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty(),
            );
        } catch (AnalyzerException $analyzerException) {
            throw new CompilerException($analyzerException, $readerResult->getCodeSnippet());
        }
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    private function emitNode(AbstractNode $node, CompileOptions $compileOptions): void
    {
        $this->fileEmitter->emitNode($node);

        $phpCode = $this->statementEmitter
            ->emitNode($node, $compileOptions->isSourceMapsEnabled())
            ->getCodeWithSourceMap();

        $this->evaluator->eval($phpCode, $compileOptions);
    }

    /**
     * Checks if we should use cached version (only for actual files in build mode).
     */
    private function shouldUseCachedVersion(string $sourceFile): bool
    {
        // Only cache in build mode to avoid path issues with requires
        $isBuildMode = Phel::hasDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::BUILD_MODE)
            && Phel::getDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::BUILD_MODE) === true;

        return $isBuildMode
            && $sourceFile !== CompileOptions::DEFAULT_SOURCE
            && file_exists($sourceFile);
    }

    /**
     * Loads compiled PHP from cache.
     *
     * @throws CompiledCodeIsMalformedException
     */
    private function loadFromCache(
        string $cachedPhpPath,
        string $sourceFile,
        CompileOptions $compileOptions,
    ): EmitterResult {
        if (!file_exists($cachedPhpPath)) {
            throw new CompiledCodeIsMalformedException('Cached PHP file does not exist: ' . $cachedPhpPath);
        }

        try {
            // Directly require the cached file (already has <?php header)
            require $cachedPhpPath;

            // Read the cached PHP code for the result
            $cachedPhpCode = file_get_contents($cachedPhpPath) ?: '';
            // Remove the <?php header from the code
            $phpCode = preg_replace('/^<\?php\s*\n/', '', $cachedPhpCode);

            return new EmitterResult(
                $compileOptions->isSourceMapsEnabled(),
                $phpCode !== null && $phpCode !== '' ? $phpCode : '',
                '',
                $sourceFile,
            );
        } catch (Throwable $throwable) {
            throw CompiledCodeIsMalformedException::fromThrowable($throwable);
        }
    }

    /**
     * Caches the compiled file result.
     */
    private function cacheResult(EmitterResult $result, CompileOptions $compileOptions): void
    {
        $phpCode = $result->getPhpCode();
        $sourceFile = $compileOptions->getSource();

        // Cache only if this is a real file (not inline code)
        if ($this->shouldUseCachedVersion($sourceFile)) {
            $this->cacheManager->storeCachedPhp($sourceFile, $phpCode);
        }
    }
}
