<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function str_contains;

final readonly class DefinitionHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private LocationConverter $locations,
        private ParamsExtractor $params,
        private SymbolResolver $symbols,
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

        $definition = $this->lookup($index, $word);
        if (!$definition instanceof Definition) {
            return null;
        }

        return $this->locations->fromDefinition($definition);
    }

    private function lookup(ProjectIndex $index, string $word): ?Definition
    {
        if (str_contains($word, '/')) {
            [$namespace, $name] = $this->symbols->split($word, $index);
            $direct = $this->apiFacade->resolveSymbol($index, $namespace, $name);
            if ($direct instanceof Definition) {
                return $direct;
            }
        }

        return $this->symbols->find($word, $index);
    }
}
