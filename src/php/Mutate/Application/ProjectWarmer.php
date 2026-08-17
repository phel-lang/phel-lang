<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use Phel\Shared\Facade\RunFacadeInterface;

/**
 * Compiles the whole load order once, in the parent, before any worker
 * exists, and writes the compiled-code cache index to disk. Without it a
 * cold cache is compiled by every worker at the same time, and two
 * processes compiling the same namespace do not agree on what they wrote
 * (the same reason `phel test --parallel` warms its shared prefix first).
 * On a warm cache this is a second of `require`s.
 *
 * @internal
 */
final readonly class ProjectWarmer
{
    public function __construct(
        private RunFacadeInterface $runFacade,
    ) {}

    /**
     * @param list<string> $loadOrder absolute `.phel` files, dependencies first
     */
    public function warm(array $loadOrder): void
    {
        foreach ($loadOrder as $file) {
            $this->runFacade->evalFile($this->runFacade->getNamespaceFromFile($file));
        }

        $this->runFacade->flushCompiledCodeCache();
    }
}
