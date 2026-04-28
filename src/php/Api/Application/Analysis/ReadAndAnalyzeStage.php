<?php

declare(strict_types=1);

namespace Phel\Api\Application\Analysis;

use Phel\Api\Domain\AnalysisStageInterface;
use Phel\Api\Transfer\Diagnostic;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Exceptions\ErrorCode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Shared\Facade\CompilerFacadeInterface;

/**
 * Second stage: read each parse tree into a Phel value, then analyze
 * it into an AST node. Emits diagnostics for analyzer/reader errors
 * but keeps going across top-level forms so one bad form doesn't
 * hide following ones.
 */
final readonly class ReadAndAnalyzeStage implements AnalysisStageInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function run(string $source, string $uri, array &$context): array
    {
        $diagnostics = [];
        $parseTrees = $context['parseTrees'] ?? [];

        foreach ($parseTrees as $parseTree) {
            if (!$parseTree instanceof NodeInterface) {
                continue;
            }

            try {
                $readerResult = $this->compilerFacade->read($parseTree);
                $this->compilerFacade->analyze(
                    $readerResult->getAst(),
                    NodeEnvironment::empty()->withReturnContext(),
                );
            } catch (ReaderException $e) {
                $diagnostics[] = $this->diagnosticFromLocation(
                    code: ($e->getErrorCode() ?? ErrorCode::READER_ERROR)->value,
                    message: $e->getMessage(),
                    uri: $uri,
                    startLine: $e->getStartLocation()?->getLine(),
                    startCol: $e->getStartLocation()?->getColumn(),
                    endLine: $e->getEndLocation()?->getLine(),
                    endCol: $e->getEndLocation()?->getColumn(),
                );
            } catch (AnalyzerException $e) {
                $diagnostics[] = $this->diagnosticFromLocation(
                    code: ($e->getErrorCode() ?? ErrorCode::INVALID_SPECIAL_FORM)->value,
                    message: $e->getMessage(),
                    uri: $uri,
                    startLine: $e->getStartLocation()?->getLine(),
                    startCol: $e->getStartLocation()?->getColumn(),
                    endLine: $e->getEndLocation()?->getLine(),
                    endCol: $e->getEndLocation()?->getColumn(),
                );
            }
        }

        return $diagnostics;
    }

    private function diagnosticFromLocation(
        string $code,
        string $message,
        string $uri,
        ?int $startLine,
        ?int $startCol,
        ?int $endLine,
        ?int $endCol,
    ): Diagnostic {
        $sl = $startLine ?? 1;
        $sc = $startCol ?? 1;

        return new Diagnostic(
            code: $code,
            severity: Diagnostic::SEVERITY_ERROR,
            message: $message,
            uri: $uri,
            startLine: $sl,
            startCol: $sc,
            endLine: $endLine ?? $sl,
            endCol: $endCol ?? $sc,
        );
    }
}
