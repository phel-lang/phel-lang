<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\Document\Document;
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
        $index = $session->projectIndex();
        if (!$index instanceof ProjectIndex) {
            return [];
        }

        $uri = $this->params->uri($params);
        $position = $this->params->position($params);
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

        [$namespace, $name] = $this->symbols->split($word, $index);
        $references = $this->apiFacade->findReferences($index, $namespace, $name);

        $result = [];
        foreach ($references as $location) {
            $result[] = $this->locations->fromLocation($location);
        }

        return $result;
    }
}
