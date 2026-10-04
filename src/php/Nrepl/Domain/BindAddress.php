<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain;

use function filter_var;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * nREPL has no authentication: whoever reaches the port runs code as the user.
 * A loopback address keeps it on this machine.
 *
 * @internal
 */
final class BindAddress
{
    public static function isLoopback(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === 'localhost' || $host === '::1') {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && str_starts_with($host, '127.');
    }
}
