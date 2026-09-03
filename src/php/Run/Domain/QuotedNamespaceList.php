<?php

declare(strict_types=1);

namespace Phel\Run\Domain;

use Phel\Shared\NamespaceInformation;

use function implode;

/**
 * Renders namespaces as the space-separated quoted symbols the generated
 * `(phel.test/run-tests …)` and `(phel.test/run-benchmarks …)` forms take as
 * their trailing arguments.
 *
 * @internal
 */
final class QuotedNamespaceList
{
    /**
     * @param list<NamespaceInformation> $namespaces
     */
    public static function of(array $namespaces): string
    {
        $quoted = [];
        foreach ($namespaces as $info) {
            $quoted[] = "'" . $info->getNamespace();
        }

        return implode(' ', $quoted);
    }
}
