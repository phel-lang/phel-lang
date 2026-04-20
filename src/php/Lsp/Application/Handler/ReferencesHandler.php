<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

final readonly class ReferencesHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private LocationConverter $locations,
        private ParamsExtractor $params,
        private SymbolResolver $symbols,
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
        $context = CursorContext::resolve($params, $session, $this->params);
        if (!$context instanceof CursorContext) {
            return [];
        }

        [$namespace, $name] = $this->symbols->split($context->word, $context->index);
        $references = $this->apiFacade->findReferences($context->index, $namespace, $name);

        $result = [];
        foreach ($references as $location) {
            $result[] = $this->locations->fromLocation($location);
        }

        return $result;
    }
}
