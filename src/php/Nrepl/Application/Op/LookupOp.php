<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Api\Transfer\PhelFunction;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Nrepl\Domain\Session\Session;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\Facade\ApiFacadeInterface;

use function implode;

/**
 * Shared implementation for `lookup`, `info`, and `eldoc` ops.
 * They all translate a symbol name to its documentation/signature record.
 */
final readonly class LookupOp implements OpHandlerInterface
{
    private const string DEFAULT_NAMESPACE = 'user';

    public function __construct(
        private ApiFacadeInterface $apiFacade,
        private string $opName = 'lookup',
        private ?SessionRegistry $sessions = null,
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
            return [OpResponse::errorDone(
                $request,
                'Missing required "sym" param for ' . $this->opName . ' op.',
                [OpStatus::NO_INFO],
            )];
        }

        $fn = $this->apiFacade->findSymbolMetadata($symbol, $this->resolveCurrentNamespace($request));

        if (!$fn instanceof PhelFunction) {
            return [OpResponse::forRequest(
                $request,
                [],
                [OpStatus::DONE, OpStatus::NO_INFO],
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

        return [OpResponse::forRequest(
            $request,
            ['info' => $info, 'eldoc' => $fn->signatures],
            [OpStatus::DONE],
        )];
    }

    private function resolveCurrentNamespace(OpRequest $request): string
    {
        $explicit = $request->stringParam('ns');
        if ($explicit !== '') {
            return $explicit;
        }

        if ($this->sessions instanceof SessionRegistry && $request->session !== null) {
            $session = $this->sessions->get($request->session);
            if ($session instanceof Session) {
                return $session->namespace();
            }
        }

        return self::DEFAULT_NAMESPACE;
    }
}
