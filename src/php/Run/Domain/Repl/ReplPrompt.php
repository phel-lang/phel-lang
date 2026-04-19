<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

use function sprintf;

final class ReplPrompt
{
    private const string DEFAULT_NAMESPACE = 'user';

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
        if (!GlobalEnvironmentSingleton::isInitialized()) {
            return self::DEFAULT_NAMESPACE;
        }

        $ns = GlobalEnvironmentSingleton::getInstance()->getNs();

        return $ns !== '' ? $ns : self::DEFAULT_NAMESPACE;
    }
}
