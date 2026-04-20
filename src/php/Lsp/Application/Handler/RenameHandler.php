<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_string;
use function strlen;

/**
 * Rename a symbol across the workspace. Uses findReferences for all
 * call-sites plus the definition site itself.
 */
final readonly class RenameHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private PositionConverter $positions,
        private UriConverter $uris,
        private ParamsExtractor $params,
        private SymbolResolver $symbols,
    ) {}

    public function method(): string
    {
        return 'textDocument/rename';
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

        $newName = is_string($params['newName'] ?? null) ? $params['newName'] : '';
        if ($newName === '') {
            return null;
        }

        $uri = $this->params->uri($params);
        $position = $this->params->position($params);
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

        [$namespace, $name] = $this->symbols->split($word, $index);
        $references = $this->apiFacade->findReferences($index, $namespace, $name);
        $definition = $this->apiFacade->resolveSymbol($index, $namespace, $name);

        return $this->buildWorkspaceEdit($references, $definition, $name, $newName);
    }

    /**
     * @param list<Location> $references
     *
     * @return array{changes: array<string, list<array{range: array{start: array{line: int, character: int}, end: array{line: int, character: int}}, newText: string}>>}
     */
    private function buildWorkspaceEdit(
        array $references,
        ?Definition $definition,
        string $oldName,
        string $newName,
    ): array {
        $nameLen = strlen($oldName);
        /** @var array<string, list<array{range: array{start: array{line: int, character: int}, end: array{line: int, character: int}}, newText: string}>> $changes */
        $changes = [];

        if ($definition instanceof Definition) {
            $uri = $this->uris->isFileUri($definition->uri) ? $definition->uri : $this->uris->fromFilePath($definition->uri);
            $changes[$uri][] = [
                'range' => $this->positions->toLspRange(
                    $definition->line,
                    $definition->col,
                    $definition->line,
                    $definition->col + $nameLen,
                ),
                'newText' => $newName,
            ];
        }

        foreach ($references as $location) {
            $uri = $this->uris->isFileUri($location->uri) ? $location->uri : $this->uris->fromFilePath($location->uri);
            $changes[$uri][] = [
                'range' => $this->positions->toLspRange(
                    $location->line,
                    $location->col,
                    $location->endLine > 0 ? $location->endLine : $location->line,
                    $location->endCol > 0 ? $location->endCol : $location->col + $nameLen,
                ),
                'newText' => $newName,
            ];
        }

        return ['changes' => $changes];
    }
}
