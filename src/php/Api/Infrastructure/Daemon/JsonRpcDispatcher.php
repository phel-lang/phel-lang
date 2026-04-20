<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Daemon;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\Diagnostic;
use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\ProjectIndex;
use Throwable;

use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Dispatches a JSON-RPC-style request to the ApiFacade.
 *
 * Request shape:  {"id": <any>, "method": "analyzeSource", "params": {...}}
 * Response shape: {"id": <any>, "result": ...} | {"id": <any>, "error": {code, message}}
 *
 * The dispatcher owns a single in-memory ProjectIndex created by `indexProject`
 * or `setIndex` so subsequent resolveSymbol / findReferences / completeAtPoint
 * calls can reference it by a session-stable handle rather than re-sending a
 * serialized index on every request.
 */
final class JsonRpcDispatcher
{
    private ?ProjectIndex $cachedIndex = null;

    public function __construct(
        private readonly ApiFacade $facade,
    ) {}

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function dispatch(array $request): array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        if (!is_array($params)) {
            $params = [];
        }

        if (!is_string($method) || $method === '') {
            return $this->errorResponse($id, -32600, 'Missing method');
        }

        try {
            $result = $this->invoke($method, $params);
            return [
                'id' => $id,
                'result' => $result,
            ];
        } catch (UnknownMethodException $e) {
            return $this->errorResponse($id, -32601, $e->getMessage());
        } catch (Throwable $e) {
            return $this->errorResponse($id, -32000, $e->getMessage());
        }
    }

    public function setIndex(ProjectIndex $index): void
    {
        $this->cachedIndex = $index;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function invoke(string $method, array $params): mixed
    {
        return match ($method) {
            'analyzeSource' => array_map(
                static fn(Diagnostic $d): array => $d->toArray(),
                $this->facade->analyzeSource(
                    (string) ($params['source'] ?? ''),
                    (string) ($params['uri'] ?? ''),
                ),
            ),
            'indexProject' => $this->indexProject($params),
            'resolveSymbol' => $this->resolveSymbol($params),
            'findReferences' => $this->findReferences($params),
            'completeAtPoint' => $this->completeAtPoint($params),
            default => throw new UnknownMethodException(sprintf('Unknown method: %s', $method)),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function indexProject(array $params): array
    {
        /** @var list<string> $dirs */
        $dirs = [];
        $raw = $params['srcDirs'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $dir) {
                if (is_string($dir)) {
                    $dirs[] = $dir;
                }
            }
        }

        $this->cachedIndex = $this->facade->indexProject($dirs);

        return $this->cachedIndex->toArray();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveSymbol(array $params): ?array
    {
        $index = $this->requireIndex();
        $definition = $this->facade->resolveSymbol(
            $index,
            (string) ($params['namespace'] ?? ''),
            (string) ($params['symbol'] ?? ''),
        );

        return $definition?->toArray();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function findReferences(array $params): array
    {
        $index = $this->requireIndex();
        $locations = $this->facade->findReferences(
            $index,
            (string) ($params['namespace'] ?? ''),
            (string) ($params['symbol'] ?? ''),
        );

        return array_map(static fn(Location $loc): array => $loc->toArray(), $locations);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function completeAtPoint(array $params): array
    {
        $index = $this->requireIndex();

        $completions = $this->facade->completeAtPoint(
            (string) ($params['source'] ?? ''),
            is_int($params['line'] ?? null) ? $params['line'] : 1,
            is_int($params['col'] ?? null) ? $params['col'] : 1,
            $index,
        );

        return array_map(static fn(Completion $c): array => $c->toArray(), $completions);
    }

    private function requireIndex(): ProjectIndex
    {
        if (!$this->cachedIndex instanceof ProjectIndex) {
            $this->cachedIndex = new ProjectIndex([], []);
        }

        return $this->cachedIndex;
    }

    /**
     * @return array{id: mixed, error: array{code: int, message: string}}
     */
    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
