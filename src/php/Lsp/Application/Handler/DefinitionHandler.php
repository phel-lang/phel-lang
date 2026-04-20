<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Definition;
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

final readonly class DefinitionHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private LocationConverter $locations,
    ) {}

    public function method(): string
    {
        return 'textDocument/definition';
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
            return null;
        }

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

        $definition = $this->lookup($index, $word);
        if (!$definition instanceof Definition) {
            return null;
        }

        return $this->locations->fromDefinition($definition);
    }

    private function lookup(ProjectIndex $index, string $word): ?Definition
    {
        if (str_contains($word, '/')) {
            $parts = explode('/', $word, 2);
            $namespace = $parts[0];
            $name = $parts[1] ?? '';
            $direct = $this->apiFacade->resolveSymbol($index, $namespace, $name);
            if ($direct instanceof Definition) {
                return $direct;
            }

            return $index->definitions[$word] ?? null;
        }

        foreach ($index->definitions as $def) {
            if ($def->name === $word) {
                return $def;
            }
        }

        return null;
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
