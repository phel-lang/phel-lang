<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

/**
 * What a parallel run tells the command once every namespace is in.
 *
 * @internal
 */
final readonly class ParallelRunOutcome
{
    public function __construct(
        public bool $ok,
        public bool $focused,
    ) {}
}
