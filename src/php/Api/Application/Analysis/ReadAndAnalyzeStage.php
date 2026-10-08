<?php

declare(strict_types=1);

namespace Phel\Api\Application\Analysis;

use Phel\Api\Domain\AnalysisStageInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\Exceptions\UnevaluatedDefinitionException;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use Throwable;

use function file_get_contents;
use function in_array;
use function is_array;
use function is_string;

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
        private LoadedSourceLocator $loadedSourceLocator = new LoadedSourceLocator(),
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
                $this->compilerFacade->rejectSupersededForms($ast);
                $this->analyzeForm($ast, $uri);
            } catch (ReaderException $e) {
                $diagnostics[] = Diagnostic::fromLocatedException($e, ErrorCode::READER_ERROR, $uri);
            } catch (AnalyzerException $e) {
                if ($this->isAnalysisArtefact($e)) {
                    continue;
                }

                $diagnostics[] = Diagnostic::fromLocatedException($e, ErrorCode::INVALID_SPECIAL_FORM, $uri);
            }
        }

        return $diagnostics;
    }

    /**
     * This pass never evaluates a `def`, so a macro it defined has no fn yet
     * and a macro that runs its arguments may call a fn that is still `null`.
     * The compiler marks those causes; anything else is an error in the source.
     */
    private function isAnalysisArtefact(AnalyzerException $e): bool
    {
        for ($cause = $e->getPrevious(); $cause instanceof Throwable; $cause = $cause->getPrevious()) {
            if ($cause instanceof UnevaluatedDefinitionException) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param bool|float|int|string|TypeInterface|null $form
     * @param list<string>                             $loading files whose load is in progress, so a cycle stops
     */
    private function analyzeForm(mixed $form, string $uri, array $loading = []): void
    {
        $this->compilerFacade->analyze(
            $form,
            $this->compilerFacade->emptyNodeEnvironment()->withReturnContext(),
        );

        if (!$form instanceof PersistentListInterface) {
            return;
        }

        $head = $form->first();
        if ($head instanceof Symbol && $head->getFullName() === Symbol::NAME_LOAD && is_string($form->get(1))) {
            $this->analyzeLoadedFile($form->get(1), $uri, $loading);
        }
    }

    /**
     * The loaded file's own diagnostics belong to an analysis of that file.
     *
     * @param list<string> $loading
     */
    private function analyzeLoadedFile(string $pathArg, string $callerUri, array $loading): void
    {
        $globalEnv = $this->compilerFacade->getGlobalEnvironment();
        $callerNamespace = $globalEnv->getNs();
        $path = $this->loadedSourceLocator->locate($callerNamespace, $pathArg, $callerUri);
        if ($path === null || in_array($path, $loading, true)) {
            return;
        }

        try {
            foreach ($this->compilerFacade->readFormsBestEffort((string) file_get_contents($path), $path) as $form) {
                try {
                    $this->analyzeForm($form, $path, [...$loading, $callerUri, $path]);
                } catch (AnalyzerException) {
                }
            }
        } finally {
            $globalEnv->setNs($callerNamespace);
        }
    }
}
