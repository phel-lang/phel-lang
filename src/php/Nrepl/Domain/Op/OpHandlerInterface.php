<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Op;

interface OpHandlerInterface
{
    public function name(): string;

    /**
     * @return list<OpResponse>
     */
    public function handle(OpRequest $request): array;
}
