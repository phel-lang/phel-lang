<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Shared\Facade\RunFacadeInterface;

final readonly class DescribeOp implements OpHandlerInterface
{
    public function __construct(
        private OpDispatcher $dispatcher,
        private RunFacadeInterface $runFacade,
    ) {}

    public function name(): string
    {
        return 'describe';
    }

    public function handle(OpRequest $request): array
    {
        $ops = [];
        foreach ($this->dispatcher->knownOps() as $op) {
            $ops[$op] = [];
        }

        $version = $this->runFacade->getVersion();

        return [OpResponse::build(
            $request->id,
            $request->session,
            [
                'ops' => $ops,
                'versions' => [
                    'phel' => ['version-string' => $version],
                    'nrepl' => ['version-string' => '0.1.0', 'major' => 0, 'minor' => 1, 'incremental' => 0],
                ],
                'aux' => [],
            ],
            ['done'],
        )];
    }
}
