<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Shared\FrameworkNamespaces;
use Phel\Shared\Munge;

/**
 * @internal
 */
final class RegistryNamespace
{
    /**
     * The registry keeps no source spelling: `my_app` and `my-app` share one key
     * yet are different namespaces to `require`. Only the stdlib, which never
     * writes `_` in a namespace name, can be decoded safely.
     */
    public static function sourceName(string $registryNs): string
    {
        $canonical = Munge::canonicalNs($registryNs);

        return FrameworkNamespaces::isPhel($canonical)
            ? new Munge()->decodeNs($canonical)
            : $canonical;
    }
}
