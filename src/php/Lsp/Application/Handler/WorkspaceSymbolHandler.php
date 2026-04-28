<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\SymbolInformationBuilder;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_string;
use function str_contains;
use function strtolower;

final readonly class WorkspaceSymbolHandler implements HandlerInterface
{
    public function __construct(
        private SymbolInformationBuilder $symbolBuilder,
    ) {}

    public function method(): string
    {
        return 'workspace/symbol';
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

        $query = is_string($params['query'] ?? null) ? strtolower($params['query']) : '';

        $symbols = [];
        foreach ($index->definitions as $def) {
            if ($query !== '' && !str_contains(strtolower($def->name), $query)) {
                continue;
            }

            $symbols[] = $this->symbolBuilder->fromDefinitionWithContainer($def);
        }

        return $symbols;
    }
}
