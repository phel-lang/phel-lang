<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Shared\Facade\CompilerFacadeInterface;
use Throwable;

/**
 * @internal
 */
final readonly class RepairValidator
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private DelimiterScanner $scanner,
    ) {}

    public function isValid(string $code, string $source): bool
    {
        try {
            $this->compilerFacade->parseAll($this->compilerFacade->lexString($code, $source));
        } catch (Throwable) {
            return false;
        }

        try {
            return $this->scanner->scan($code, $source)->isBalanced();
        } catch (Throwable) {
            return false;
        }
    }
}
