<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Convert;

use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\PhelFunction;

/**
 * Convert Api Completion / PhelFunction into LSP CompletionItem.
 *
 * LSP CompletionItemKind values: 1 = Text, 2 = Method, 3 = Function,
 * 6 = Variable, 14 = Keyword, ...
 */
final class CompletionConverter
{
    public const int KIND_TEXT = 1;

    public const int KIND_METHOD = 2;

    public const int KIND_FUNCTION = 3;

    public const int KIND_VARIABLE = 6;

    public const int KIND_MODULE = 9;

    public const int KIND_KEYWORD = 14;

    /**
     * @return array{label: string, kind: int, detail: string, documentation: string}
     */
    public function fromCompletion(Completion $completion): array
    {
        return [
            'label' => $completion->label,
            'kind' => $this->kindForCompletion($completion->kind),
            'detail' => $completion->detail,
            'documentation' => $completion->documentation,
        ];
    }

    /**
     * @return array{label: string, kind: int, detail: string, documentation: string}
     */
    public function fromPhelFunction(PhelFunction $fn): array
    {
        $detail = $fn->signatures === [] ? $fn->namespace : $fn->signatures[0];

        return [
            'label' => $fn->name,
            'kind' => self::KIND_FUNCTION,
            'detail' => $detail,
            'documentation' => $fn->doc,
        ];
    }

    private function kindForCompletion(string $kind): int
    {
        return match ($kind) {
            Completion::KIND_LOCAL => self::KIND_VARIABLE,
            Completion::KIND_GLOBAL => self::KIND_FUNCTION,
            Completion::KIND_MACRO => self::KIND_METHOD,
            Completion::KIND_REQUIRE => self::KIND_MODULE,
            Completion::KIND_KEYWORD => self::KIND_KEYWORD,
            default => self::KIND_TEXT,
        };
    }
}
