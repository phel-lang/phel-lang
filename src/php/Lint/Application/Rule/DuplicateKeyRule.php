<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserNode\InnerNodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\KeywordNode;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\NumberNode;
use Phel\Compiler\Domain\Parser\ParserNode\StringNode;
use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

use Throwable;

use function sprintf;

/**
 * Scans literal map `{...}` parse-tree nodes for duplicate keys. Works on
 * the pre-read parse tree because the reader silently de-duplicates
 * literal maps — so by the time the read form reaches rules, duplicates
 * are gone.
 */
final readonly class DuplicateKeyRule implements LintRuleInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function code(): string
    {
        return RuleRegistry::DUPLICATE_KEY;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $result = [];

        try {
            $tokenStream = $this->compilerFacade->lexString($analysis->source, $analysis->uri);
            while (true) {
                try {
                    $parseTree = $this->compilerFacade->parseNext($tokenStream);
                } catch (AbstractParserException) {
                    break;
                }

                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                $this->walkParse($parseTree, $analysis->uri, $result);
            }
        } catch (Throwable) {
            // Best effort: if lexing fails, other rules will already flag it.
        }

        return $result;
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function walkParse(NodeInterface $node, string $uri, array &$result): void
    {
        if ($node instanceof ListNode) {
            if ($node->getTokenType() === Token::T_OPEN_BRACE) {
                $this->inspectMapLiteral($node, $uri, $result);
            }

            foreach ($node->getChildren() as $child) {
                $this->walkParse($child, $uri, $result);
            }

            return;
        }

        if ($node instanceof InnerNodeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->walkParse($child, $uri, $result);
            }
        }
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function inspectMapLiteral(ListNode $mapNode, string $uri, array &$result): void
    {
        $keys = [];
        $expectingKey = true;
        foreach ($mapNode->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            if ($expectingKey) {
                $keys[] = $child;
            }

            $expectingKey = !$expectingKey;
        }

        $seen = [];
        foreach ($keys as $key) {
            $repr = $this->keyRepr($key);
            if ($repr === null) {
                continue;
            }

            if (isset($seen[$repr])) {
                $start = $key->getStartLocation();
                $end = $key->getEndLocation();

                $result[] = new Diagnostic(
                    code: $this->code(),
                    severity: Diagnostic::SEVERITY_WARNING,
                    message: sprintf('Duplicate map key: %s.', $repr),
                    uri: $uri,
                    startLine: $start->getLine(),
                    startCol: $start->getColumn(),
                    endLine: $end->getLine(),
                    endCol: $end->getColumn(),
                );
            } else {
                $seen[$repr] = true;
            }
        }
    }

    private function keyRepr(NodeInterface $node): ?string
    {
        if ($node instanceof KeywordNode) {
            $value = $node->getValue();
            $ns = $value->getNamespace();

            return ':' . ($ns === null || $ns === '' ? '' : $ns . '/') . $value->getName();
        }

        if ($node instanceof SymbolNode) {
            $value = $node->getValue();
            $ns = $value->getNamespace();

            return ($ns === null || $ns === '' ? '' : $ns . '/') . $value->getName();
        }

        if ($node instanceof StringNode) {
            return '"' . $node->getValue() . '"';
        }

        if ($node instanceof NumberNode) {
            return (string) $node->getValue();
        }

        return null;
    }
}
