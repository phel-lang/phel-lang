<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\PhelFunction;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function implode;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function str_contains;

/**
 * Resolves a symbol under the cursor and returns its signature + docstring
 * formatted as a Markdown hover tooltip.
 */
final readonly class HoverHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
    ) {}

    public function method(): string
    {
        return 'textDocument/hover';
    }

    public function isNotification(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function handle(array $params, Session $session): mixed
    {
        $uri = $this->extractUri($params);
        $position = $this->extractPosition($params);
        if ($uri === '' || $position === null) {
            return null;
        }

        $document = $session->documents()->get($uri);
        if (!$document instanceof Document) {
            return null;
        }

        $word = $document->wordAt($position);
        if ($word === '') {
            return null;
        }

        $markdown = $this->markdownFor($word, $session->projectIndex());
        if ($markdown === null) {
            return null;
        }

        return [
            'contents' => [
                'kind' => 'markdown',
                'value' => $markdown,
            ],
        ];
    }

    private function markdownFor(string $word, ?ProjectIndex $index): ?string
    {
        $projectDefinition = $this->findProjectDefinition($word, $index);
        if ($projectDefinition instanceof Definition) {
            return $this->renderDefinition($projectDefinition);
        }

        foreach ($this->apiFacade->getPhelFunctions(['phel\\core']) as $fn) {
            if ($fn->name === $word || $fn->nameWithNamespace() === $word) {
                return $this->renderPhelFunction($fn);
            }
        }

        return null;
    }

    private function findProjectDefinition(string $word, ?ProjectIndex $index): ?Definition
    {
        if (!$index instanceof ProjectIndex) {
            return null;
        }

        if (str_contains($word, '/')) {
            return $index->definitions[$word] ?? null;
        }

        foreach ($index->definitions as $def) {
            if ($def->name === $word) {
                return $def;
            }
        }

        return null;
    }

    private function renderDefinition(Definition $def): string
    {
        $lines = [];
        $fullName = $def->namespace !== '' ? $def->namespace . '/' . $def->name : $def->name;
        $lines[] = sprintf('**%s** _(%s)_', $fullName, $def->kind);

        if ($def->signature !== []) {
            $lines[] = '';
            $lines[] = '```phel';
            foreach ($def->signature as $arity) {
                $lines[] = sprintf('(%s %s)', $def->name, $arity);
            }

            $lines[] = '```';
        }

        if ($def->docstring !== '') {
            $lines[] = '';
            $lines[] = $def->docstring;
        }

        return implode("\n", $lines);
    }

    private function renderPhelFunction(PhelFunction $fn): string
    {
        $lines = [];
        $lines[] = sprintf('**%s** _(phel/%s)_', $fn->name, $fn->namespace);

        if ($fn->signatures !== []) {
            $lines[] = '';
            $lines[] = '```phel';
            foreach ($fn->signatures as $sig) {
                $lines[] = $sig;
            }

            $lines[] = '```';
        }

        if ($fn->doc !== '') {
            $lines[] = '';
            $lines[] = $fn->doc;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function extractUri(array $params): string
    {
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return '';
        }

        return is_string($textDocument['uri'] ?? null) ? $textDocument['uri'] : '';
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{line: int, character: int}|null
     */
    private function extractPosition(array $params): ?array
    {
        $position = $params['position'] ?? null;
        if (!is_array($position)) {
            return null;
        }

        $line = $position['line'] ?? null;
        $character = $position['character'] ?? null;
        if (!is_int($line) || !is_int($character)) {
            return null;
        }

        return ['line' => $line, 'character' => $character];
    }
}
