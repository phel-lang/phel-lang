<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\SymbolMetadataFinderInterface;
use Phel\Shared\Api\PhelFunction;

use function array_slice;
use function count;
use function max;
use function min;
use function str_starts_with;
use function substr;

/**
 * LSP SignatureHelp for the plain Phel function call enclosing the cursor
 * (e.g. `(map f xs)`) — the complement to
 * {@see PhpInteropDocResolver::signatureAt()}, which only covers `php/...`
 * interop calls. Returns null when the cursor is not inside a resolvable Phel
 * call, so the handler can fall through.
 */
final readonly class PhelSignatureResolver
{
    public function __construct(
        private SymbolMetadataFinderInterface $finder,
        private PhpFormTokenizer $tokenizer = new PhpFormTokenizer(),
    ) {}

    /**
     * @return array{signatures: list<array{label: string, parameters: list<array{label: string}>, documentation?: string}>, activeSignature: int, activeParameter: int}|null
     */
    public function signatureAt(string $source, int $line, int $col, string $currentNs = 'user'): ?array
    {
        $before = CursorText::before($source, $line, $col);
        $open = CursorText::openParenPositions($before);
        if ($open === []) {
            return null;
        }

        [$tokens, $endsOpen] = $this->tokenizer->topLevel(
            substr($before, $open[count($open) - 1] + 1),
            balanceCollectionLiterals: true,
        );
        if ($tokens === []) {
            return null;
        }

        $head = $tokens[0];
        // `php/...` calls belong to PhpInteropDocResolver; defer so this resolver
        // never shadows interop signature help.
        if ($head === '' || str_starts_with($head, 'php/')) {
            return null;
        }

        $fn = $this->finder->find($head, $currentNs);
        if (!$fn instanceof PhelFunction) {
            return null;
        }

        $arities = $fn->signatures;
        if ($arities === []) {
            return null;
        }

        // tokens = [head, arg0, arg1, ...]: drop the head; a half-typed final
        // token (no trailing space) is the arg the caret sits on, not a new one.
        $activeParameter = max(0, count($tokens) - 1 - ($endsOpen ? 1 : 0));

        return $this->response($arities, $fn->doc, $activeParameter);
    }

    /**
     * @param non-empty-list<string> $arities
     *
     * @return array{signatures: list<array{label: string, parameters: list<array{label: string}>, documentation?: string}>, activeSignature: int, activeParameter: int}
     */
    private function response(array $arities, string $doc, int $activeParameter): array
    {
        $signatures = [];
        foreach ($arities as $signature) {
            $information = [
                'label' => $signature,
                'parameters' => $this->parameterInformation($this->parameters($signature)),
            ];
            if ($doc !== '') {
                $information['documentation'] = $doc;
            }

            $signatures[] = $information;
        }

        $activeSignature = $this->bestArity($signatures, $activeParameter);
        $paramCount = count($signatures[$activeSignature]['parameters']);

        return [
            'signatures' => $signatures,
            'activeSignature' => $activeSignature,
            'activeParameter' => $paramCount === 0 ? 0 : min($activeParameter, $paramCount - 1),
        ];
    }

    /**
     * Pick the arity whose parameters can hold the active argument: the
     * smallest signature with more parameters than the active index, falling
     * back to the largest (variadic) arity when the caret is past them all.
     *
     * @param non-empty-list<array{label: string, parameters: list<array{label: string}>, documentation?: string}> $signatures
     */
    private function bestArity(array $signatures, int $activeParameter): int
    {
        $covering = null;
        $coveringCount = 0;
        $largest = 0;
        $largestCount = -1;

        foreach ($signatures as $index => $signature) {
            $count = count($signature['parameters']);
            if ($count > $largestCount) {
                $largest = $index;
                $largestCount = $count;
            }

            if ($count > $activeParameter && ($covering === null || $count < $coveringCount)) {
                $covering = $index;
                $coveringCount = $count;
            }
        }

        return $covering ?? $largest;
    }

    /**
     * Parameter names of a signature string like `(map f coll & colls)`,
     * keeping a destructured `[a b]` / `{:keys [a]}` parameter whole and
     * dropping the variadic `&` marker (it labels no single argument).
     *
     * @return list<string>
     */
    private function parameters(string $signature): array
    {
        $inner = $signature;
        if (str_starts_with($inner, '(')) {
            $inner = substr($inner, 1);
        }

        if ($inner !== '' && str_ends_with($inner, ')')) {
            $inner = substr($inner, 0, -1);
        }

        [$tokens] = $this->tokenizer->topLevel($inner, balanceCollectionLiterals: true);

        // tokens = [name, param0, ...]: drop the function name, then the marker.
        $params = [];
        foreach (array_slice($tokens, 1) as $token) {
            if ($token !== '&') {
                $params[] = $token;
            }
        }

        return $params;
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<array{label: string}>
     */
    private function parameterInformation(array $parameters): array
    {
        $information = [];
        foreach ($parameters as $parameter) {
            $information[] = ['label' => $parameter];
        }

        return $information;
    }
}
