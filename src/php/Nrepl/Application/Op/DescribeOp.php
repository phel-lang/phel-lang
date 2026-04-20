<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Shared\Facade\RunFacadeInterface;

final readonly class DescribeOp implements OpHandlerInterface
{
    public const string NREPL_VERSION = '0.1.0';

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

        return [OpResponse::forRequest(
            $request,
            [
                'ops' => $ops,
                'versions' => [
                    'phel' => ['version-string' => $this->runFacade->getVersion()],
                    'nrepl' => [
                        'version-string' => self::NREPL_VERSION,
                        'major' => 0,
                        'minor' => 1,
                        'incremental' => 0,
                    ],
                ],
                'aux' => [],
            ],
            [OpStatus::DONE],
        )];
    }
}
