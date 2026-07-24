<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\Token;
use Throwable;

use function str_ends_with;
use function str_starts_with;

/**
 * Flags a comment that sits alone on its line but opens with a single `;`.
 *
 * The convention (shared with Clojure) reserves the two forms by position:
 * `;` trails code on the same line, `;;` (or more) owns the whole line.
 *
 * Works on the token stream rather than the source text because only the
 * lexer knows which `;` actually starts a comment: a `;` inside a string
 * literal, a regex literal or a `#| ... |#` block never yields a comment
 * token, so it can never be flagged. Bare `#` line comments are out of
 * scope; the lexer already deprecates them.
 */
final readonly class CommentStyleRule implements LintRuleInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function code(): string
    {
        return RuleRegistry::COMMENT_STYLE;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $result = [];

        try {
            $tokenStream = $this->compilerFacade->lexString($analysis->source, $analysis->uri);

            // A comment is "standalone" when nothing but indentation precedes
            // it on its line. Newlines and line comments are the only tokens
            // that can end a line, so tracking them is enough; whitespace is
            // indentation and leaves the flag untouched.
            $atLineStart = true;
            foreach ($tokenStream as $token) {
                $type = $token->getType();

                if ($type === Token::T_WHITESPACE) {
                    continue;
                }

                if ($type === Token::T_NEWLINE) {
                    $atLineStart = true;

                    continue;
                }

                if ($type !== Token::T_COMMENT) {
                    $atLineStart = false;

                    continue;
                }

                $code = $token->getCode();
                if ($atLineStart && $this->isSingleSemicolon($code)) {
                    $result[] = $this->diagnostic($token, $analysis->uri);
                }

                // `;` comments swallow their trailing newline (except at EOF);
                // a `#| ... |#` block does not, so code may follow it inline.
                $atLineStart = str_ends_with($code, "\n");
            }
        } catch (Throwable) {
            // Best effort: a file that does not lex is already reported by the
            // parse/analyze path, so staying silent avoids a duplicate error.
        }

        return $result;
    }

    private function isSingleSemicolon(string $code): bool
    {
        return str_starts_with($code, ';') && !str_starts_with($code, ';;');
    }

    private function diagnostic(Token $token, string $uri): Diagnostic
    {
        $start = $token->getStartLocation();

        return new Diagnostic(
            code: $this->code(),
            severity: Diagnostic::SEVERITY_WARNING,
            message: "Standalone comment should start with ';;'; a single ';' is reserved for comments that trail code on the same line.",
            uri: $uri,
            startLine: $start->getLine(),
            startCol: $start->getColumn(),
            endLine: $start->getLine(),
            endCol: $start->getColumn() + 1,
        );
    }
}
