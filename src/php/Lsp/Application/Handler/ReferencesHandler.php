<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function explode;
use function is_array;
use function is_int;
use function is_string;
use function str_contains;

final readonly class ReferencesHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private LocationConverter $locations,
    ) {}

    public function method(): string
    {
        return 'textDocument/references';
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
        $index = $session->projectIndex();
        if (!$index instanceof ProjectIndex) {
            return [];
        }

        $uri = $this->extractUri($params);
        $position = $this->extractPosition($params);
        if ($uri === '' || $position === null) {
            return [];
        }

        $document = $session->documents()->get($uri);
        if (!$document instanceof Document) {
            return [];
        }

        $word = $document->wordAt($position);
        if ($word === '') {
            return [];
        }

        [$namespace, $name] = $this->splitSymbol($word, $index);
        $references = $this->apiFacade->findReferences($index, $namespace, $name);

        $result = [];
        foreach ($references as $location) {
            $result[] = $this->locations->fromLocation($location);
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitSymbol(string $word, ProjectIndex $index): array
    {
        if (str_contains($word, '/')) {
            $parts = explode('/', $word, 2);
            return [$parts[0], $parts[1] ?? ''];
        }

        foreach ($index->definitions as $def) {
            if ($def->name === $word) {
                return [$def->namespace, $def->name];
            }
        }

        // Fallback: treat the bare name as living in the unknown namespace.
        return ['', $word];
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
