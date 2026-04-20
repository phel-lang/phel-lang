<?php

declare(strict_types=1);

namespace Phel\Api\Application\Analysis;

use Phel\Api\Domain\AnalysisStageInterface;
use Phel\Api\Transfer\Diagnostic;
use Phel\Compiler\Domain\Exceptions\ErrorCode;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

/**
 * First stage of the analysis pipeline: lex + parse the source.
 *
 * Collects parse-tree nodes into $context['parseTrees'] for later stages.
 */
final readonly class LexAndParseStage implements AnalysisStageInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function run(string $source, string $uri, array &$context): array
    {
        $diagnostics = [];
        $parseTrees = [];

        try {
            $tokenStream = $this->compilerFacade->lexString($source, $uri);
            while (true) {
                try {
                    $parseTree = $this->compilerFacade->parseNext($tokenStream);
                } catch (AbstractParserException $e) {
                    $diagnostics[] = $this->parseErrorDiagnostic($e, $uri);
                    break;
                }

                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                $parseTrees[] = $parseTree;
            }
        } catch (LexerValueException $lexerValueException) {
            $diagnostics[] = new Diagnostic(
                code: ErrorCode::LEXER_ERROR->value,
                severity: Diagnostic::SEVERITY_ERROR,
                message: $lexerValueException->getMessage(),
                uri: $uri,
                startLine: 1,
                startCol: 1,
                endLine: 1,
                endCol: 1,
            );
        }

        $context['parseTrees'] = $parseTrees;

        return $diagnostics;
    }

    private function parseErrorDiagnostic(AbstractParserException $e, string $uri): Diagnostic
    {
        $start = $e->getStartLocation();
        $end = $e->getEndLocation();

        return new Diagnostic(
            code: ($e->getErrorCode() ?? ErrorCode::PARSER_ERROR)->value,
            severity: Diagnostic::SEVERITY_ERROR,
            message: $e->getMessage(),
            uri: $uri,
            startLine: $start?->getLine() ?? 1,
            startCol: $start?->getColumn() ?? 1,
            endLine: $end?->getLine() ?? ($start?->getLine() ?? 1),
            endCol: $end?->getColumn() ?? ($start?->getColumn() ?? 1),
        );
    }
}
