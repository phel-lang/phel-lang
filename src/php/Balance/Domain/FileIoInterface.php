<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

use Phel\Balance\Domain\Exception\BalanceSourceException;

/**
 * @internal
 */
interface FileIoInterface
{
    /**
     * @throws BalanceSourceException
     */
    public function read(string $path): string;

    /**
     * @throws BalanceSourceException
     */
    public function write(string $path, string $contents): void;
}
