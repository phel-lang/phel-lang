<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Document;

use function array_keys;

/**
 * In-memory URI -> Document cache.
 *
 * Mutation is performed through open() / update() / close() so handlers don't
 * need to know about storage internals.
 */
final class DocumentStore
{
    /** @var array<string, Document> */
    private array $documents = [];

    public function open(string $uri, string $languageId, int $version, string $text): Document
    {
        $document = new Document($uri, $languageId, $version, $text);
        $this->documents[$uri] = $document;

        return $document;
    }

    public function replace(string $uri, int $version, string $text): ?Document
    {
        $document = $this->get($uri);
        if (!$document instanceof Document) {
            return null;
        }

        $document->update($text, $version);

        return $document;
    }

    public function get(string $uri): ?Document
    {
        return $this->documents[$uri] ?? null;
    }

    public function close(string $uri): void
    {
        unset($this->documents[$uri]);
    }

    /**
     * @return list<string>
     */
    public function uris(): array
    {
        return array_keys($this->documents);
    }
}
