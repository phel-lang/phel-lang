<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\HandlerInterface;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Shared\Api\Definition;
use Phel\Shared\Api\Location;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Facade\ApiFacadeInterface;

use function str_contains;

/**
 * @internal
 */
final readonly class DefinitionHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacadeInterface $apiFacade,
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
     *
     * @return array<string, mixed>|null
     */
    public function handle(array $params, Session $session): ?array
    {
        $context = CursorContext::resolve($params, $session, $this->params);
        if (!$context instanceof CursorContext) {
            return null;
        }

        $definition = $this->lookup($context->index, $context->word);
        if ($definition instanceof Definition) {
            return $this->locations->fromDefinition($definition);
        }

        $namespace = $context->index->namespaceLocation($context->word);
        if (!$namespace instanceof Location) {
            return null;
        }

        return $this->locations->fromLocation($namespace);
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
