<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions\Hint;

use Phel\Shared\FrameworkNamespaces;
use Throwable;

use function preg_match;
use function sprintf;
use function str_contains;

/**
 * A `(:require ...)` with no source file and no close spelling is most often a
 * library that was never installed, phel.phar users especially: nothing tells
 * them libraries come from Composer. Matches the message, not the exception
 * class: the REPL and `phel eval` raise it without a located `PHEL014`.
 */
final class MissingLibraryHint implements ExceptionHintInterface
{
    /**
     * Official libraries outside the bundled stdlib. Their `phel.*` names
     * usually draw a misleading "Did you mean" for a bundled namespace.
     */
    private const array KNOWN_PACKAGES = [
        'phel.pdo' => 'phel-lang/phel-pdo',
        'phel.sql' => 'phel-lang/phel-sql',
        'phel.log' => 'phel-lang/phel-log',
    ];

    public function appliesTo(Throwable $e): bool
    {
        $namespace = $this->requiredNamespace($e);
        if ($namespace === null) {
            return false;
        }

        if ($this->knownPackage($namespace) !== null) {
            return true;
        }

        return !str_contains($e->getMessage(), 'Did you mean')
            && !FrameworkNamespaces::matches($namespace);
    }

    public function hint(Throwable $e): string
    {
        $namespace = $this->requiredNamespace($e) ?? 'the namespace';
        $package = $this->knownPackage($namespace);
        if ($package !== null) {
            return sprintf("'%s' comes from %s. Install it with composer require %s.", $namespace, $package, $package);
        }

        return sprintf(
            "if '%s' comes from a library, install it with composer require <vendor/package>. Phel reads libraries from the vendor directory.",
            $namespace,
        );
    }

    private function requiredNamespace(Throwable $e): ?string
    {
        return preg_match("/Cannot find namespace '(.+?)' required by '/", $e->getMessage(), $m) === 1 ? $m[1] : null;
    }

    private function knownPackage(string $namespace): ?string
    {
        return self::KNOWN_PACKAGES[$namespace] ?? null;
    }
}
