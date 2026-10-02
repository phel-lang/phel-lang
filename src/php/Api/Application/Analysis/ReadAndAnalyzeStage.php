<?php

declare(strict_types=1);

namespace Phel\Api\Application\Analysis;

use Phel\Api\Domain\AnalysisStageInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\TypeInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

use function is_array;

/**
 * Second stage: read each parse tree into a Phel value, then analyze
 * it into an AST node. Emits diagnostics for analyzer/reader errors
 * but keeps going across top-level forms so one bad form doesn't
 * hide following ones.
 *
 * @internal
 */
final readonly class ReadAndAnalyzeStage implements AnalysisStageInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function run(string $source, string $uri, array &$context): array
    {
        // The namespace under analysis is very likely already loaded in this
        // process: `PreloadDependenciesStage` evaluates the bundled `phel.*`
        // modules and the file's own dependencies, and a directory-wide run
        // analyses files that required one another. Re-reading a source is
        // not a redefinition, so suppress the `def` duplicate guard for the
        // whole pass instead of letting it abort the file at its first `def`.
        $globalEnv = $this->compilerFacade->getGlobalEnvironment();
        $globalEnv->enterAnalysisMode();

        try {
            return $this->analyzeParseTrees($context['parseTrees'] ?? [], $uri);
        } finally {
            $globalEnv->leaveAnalysisMode();
        }
    }

    /**
     * @return list<Diagnostic>
     */
    private function analyzeParseTrees(mixed $parseTrees, string $uri): array
    {
        $diagnostics = [];
        if (!is_array($parseTrees)) {
            $parseTrees = [];
        }

        foreach ($parseTrees as $parseTree) {
            if (!$parseTree instanceof NodeInterface) {
                continue;
            }

            try {
                $readerResult = $this->compilerFacade->read($parseTree);
                /** @var bool|float|int|string|TypeInterface|null $ast */
                $ast = $readerResult->getAst();
                $this->compilerFacade->analyze(
                    $ast,
                    $this->compilerFacade->emptyNodeEnvironment()->withReturnContext(),
                );
            } catch (ReaderException $e) {
                $diagnostics[] = Diagnostic::fromLocatedException($e, ErrorCode::READER_ERROR, $uri);
            } catch (AnalyzerException $e) {
                $diagnostics[] = Diagnostic::fromLocatedException($e, ErrorCode::INVALID_SPECIAL_FORM, $uri);
            }
        }

        return $diagnostics;
    }
}
