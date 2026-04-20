<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Api\Transfer\PhelFunction;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Shared\Facade\ApiFacadeInterface;

use function implode;

/**
 * Shared implementation for `lookup`, `info`, and `eldoc` ops.
 * They all translate a symbol name to its documentation/signature record.
 */
final class LookupOp implements OpHandlerInterface
{
    /** @var list<PhelFunction>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ApiFacadeInterface $apiFacade,
        private readonly string $opName = 'lookup',
    ) {}

    public function name(): string
    {
        return $this->opName;
    }

    public function handle(OpRequest $request): array
    {
        $symbol = $request->stringParam('sym');
        if ($symbol === '') {
            $symbol = $request->stringParam('symbol');
        }

        if ($symbol === '') {
            return [OpResponse::build(
                $request->id,
                $request->session,
                ['message' => 'Missing required "sym" param for ' . $this->opName . ' op.'],
                ['error', 'no-info', 'done'],
            )];
        }

        $fn = $this->findFunction($symbol);
        if (!$fn instanceof PhelFunction) {
            return [OpResponse::build(
                $request->id,
                $request->session,
                [],
                ['done', 'no-info'],
            )];
        }

        $info = [
            'name' => $fn->name,
            'ns' => $fn->namespace,
            'doc' => $fn->doc,
            'arglists-str' => implode(' ', $fn->signatures),
            'file' => $fn->file,
            'line' => $fn->line,
        ];

        return [OpResponse::build(
            $request->id,
            $request->session,
            ['info' => $info, 'eldoc' => $fn->signatures],
            ['done'],
        )];
    }

    private function findFunction(string $symbol): ?PhelFunction
    {
        if ($this->cache === null) {
            $this->cache = $this->apiFacade->getPhelFunctions();
        }

        foreach ($this->cache as $fn) {
            if ($fn->nameWithNamespace() === $symbol) {
                return $fn;
            }

            if ($fn->name === $symbol && ($fn->namespace === 'core' || $fn->namespace === '')) {
                return $fn;
            }
        }

        return null;
    }
}
