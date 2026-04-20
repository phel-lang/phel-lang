<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Nrepl\Domain\Session\SessionRegistry;

final readonly class CloseOp implements OpHandlerInterface
{
    public function __construct(private SessionRegistry $sessions) {}

    public function name(): string
    {
        return 'close';
    }

    public function handle(OpRequest $request): array
    {
        $target = $request->session ?? '';
        $closed = $target !== '' && $this->sessions->close($target);

        $status = $closed
            ? [OpStatus::DONE, OpStatus::SESSION_CLOSED]
            : [OpStatus::DONE, OpStatus::ERROR, OpStatus::UNKNOWN_SESSION];

        return [OpResponse::build(
            $request->id,
            $request->session,
            [],
            $status,
        )];
    }
}
