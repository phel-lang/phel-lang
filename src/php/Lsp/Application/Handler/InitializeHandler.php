<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;
use Phel\Lsp\LspConfig;

use function is_array;
use function is_string;

/**
 * Handles `initialize`. Advertises the capabilities the server implements
 * and primes the project index from the workspace root(s).
 */
final readonly class InitializeHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private UriConverter $uris,
    ) {}

    public function method(): string
    {
        return 'initialize';
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
        $session->setClientCapabilities($this->clientCapabilities($params));
        $roots = $this->extractWorkspaceRoots($params);
        $session->setWorkspaceRoots($roots);

        if ($roots !== []) {
            $session->setProjectIndex($this->apiFacade->indexProject($roots));
        }

        return [
            'capabilities' => [
                'textDocumentSync' => [
                    'openClose' => true,
                    'change' => 2, // Incremental
                    'save' => ['includeText' => false],
                ],
                'hoverProvider' => true,
                'definitionProvider' => true,
                'referencesProvider' => true,
                'completionProvider' => [
                    'triggerCharacters' => ['/', ':', '.'],
                    'resolveProvider' => false,
                ],
                'documentSymbolProvider' => true,
                'workspaceSymbolProvider' => true,
                'renameProvider' => true,
                'documentFormattingProvider' => true,
            ],
            'serverInfo' => [
                'name' => LspConfig::defaultServerName(),
                'version' => LspConfig::defaultServerVersion(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function clientCapabilities(array $params): array
    {
        $raw = $params['capabilities'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        /** @var array<string, mixed> $raw */
        return $raw;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<string>
     */
    private function extractWorkspaceRoots(array $params): array
    {
        $roots = [];
        $folders = $params['workspaceFolders'] ?? null;
        if (is_array($folders)) {
            foreach ($folders as $folder) {
                if (!is_array($folder)) {
                    continue;
                }

                $uri = $folder['uri'] ?? null;
                if (is_string($uri) && $uri !== '') {
                    $roots[] = $this->uris->toFilePath($uri);
                }
            }
        }

        if ($roots === []) {
            $rootUri = $params['rootUri'] ?? null;
            if (is_string($rootUri) && $rootUri !== '') {
                $roots[] = $this->uris->toFilePath($rootUri);
            }
        }

        if ($roots === []) {
            $rootPath = $params['rootPath'] ?? null;
            if (is_string($rootPath) && $rootPath !== '') {
                $roots[] = $rootPath;
            }
        }

        return $roots;
    }
}
