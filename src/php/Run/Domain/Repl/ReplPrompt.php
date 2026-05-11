<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Munge;

use function sprintf;

final readonly class ReplPrompt
{
    private const string DEFAULT_NAMESPACE = 'user';

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function initial(int $lineNumber): string
    {
        return sprintf('%s:%d> ', $this->currentNamespace(), $lineNumber);
    }

    public function continuation(int $lineNumber): string
    {
        return sprintf('....:%d> ', $lineNumber);
    }

    private function currentNamespace(): string
    {
        if (!$this->compilerFacade->isGlobalEnvironmentInitialized()) {
            return self::DEFAULT_NAMESPACE;
        }

        $ns = $this->compilerFacade->getGlobalEnvironment()->getNs();

        return $ns !== '' ? Munge::displayNs($ns) : self::DEFAULT_NAMESPACE;
    }
}
