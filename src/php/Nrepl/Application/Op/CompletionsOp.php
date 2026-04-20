<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Shared\Facade\ApiFacadeInterface;

final readonly class CompletionsOp implements OpHandlerInterface
{
    public function __construct(private ApiFacadeInterface $apiFacade) {}

    public function name(): string
    {
        return 'completions';
    }

    public function handle(OpRequest $request): array
    {
        $prefix = $request->stringParam('prefix');
        $results = $this->apiFacade->replCompleteWithTypes($prefix);

        $completions = [];
        foreach ($results as $result) {
            $completions[] = [
                'candidate' => $result->candidate,
                'type' => $result->type,
            ];
        }

        return [OpResponse::build(
            $request->id,
            $request->session,
            ['completions' => $completions],
            ['done'],
        )];
    }
}
