<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Nrepl\Domain\Session\SessionRegistry;

final readonly class CloneOp implements OpHandlerInterface
{
    public function __construct(private SessionRegistry $sessions) {}

    public function name(): string
    {
        return 'clone';
    }

    public function handle(OpRequest $request): array
    {
        $session = $this->sessions->create();

        return [OpResponse::forRequest(
            $request,
            ['new-session' => $session->id],
            [OpStatus::DONE],
        )];
    }
}
