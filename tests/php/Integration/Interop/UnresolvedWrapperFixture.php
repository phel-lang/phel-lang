<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Interop\PhelCallerTrait;

/**
 * Two calls a generated wrapper can make that never resolve: one into a
 * namespace the host never evaluated, one into a loaded namespace whose
 * definition is gone.
 */
final class UnresolvedWrapperFixture
{
    use PhelCallerTrait;

    public function intoUnloadedNamespace(): mixed
    {
        return $this->callPhel('my-app\\billing', 'total', 1);
    }

    public function intoMissingDefinition(): mixed
    {
        return $this->callPhel('phel\\core', 'renamed-away', 1);
    }
}
