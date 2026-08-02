<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\PhpClassLike;
use Phel\Compiler\Domain\Diagnostic\CompilerWarnings;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function preg_match;
use function sprintf;

/**
 * Detects a `def` whose name is already a loadable PHP class.
 *
 * The definition wins from there on: `SymbolResolver`'s bare-host-symbol
 * fallback resolves a Phel definition before it considers a class, so
 * `(def DateTime "shadow")` makes `(new DateTime)` fail with
 * `Class "shadow" not found` and nothing says why.
 *
 * Clojure refuses the `def` outright ("Expecting var, but RuntimeException is
 * mapped to class java.lang.RuntimeException"), which is what makes a bare
 * class name safe to write there. Phel warns rather than throws, because
 * refusing is a breaking change and the
 * [deprecation policy](../../../../../docs/stability.md) buys that with a
 * minor of notice first. The refusal belongs to the same major that drops the
 * leading `\` (#2876, #2827).
 *
 * Detection only: the stdlib suppression and the per-`(file, subject)` dedup
 * belong to {@see CompilerWarnings}, which is why this class is stateless.
 *
 * @internal
 */
final class ClassShadowWarner
{
    /**
     * A name PHP could resolve to a class. Nearly every Phel name fails it
     * (`my-fn`, `empty?`, `set!`), which matters: {@see PhpClassLike::exists()}
     * autoloads, and `def` runs thousands of times loading the stdlib alone.
     */
    private const string PHP_IDENTIFIER = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/';

    private static ?self $instance = null;

    /**
     * Shared instance. Stateless, so it exists only to spare the analyzer a
     * per-def allocation; there is nothing to reset between runs.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function maybeWarn(string $ns, Symbol $name): void
    {
        $shadowed = $name->getName();

        if (preg_match(self::PHP_IDENTIFIER, $shadowed) !== 1) {
            return;
        }

        $location = $name->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        if (!PhpClassLike::exists($shadowed)) {
            return;
        }

        CompilerWarnings::warnOnceForSource(
            $location->getFile(),
            $ns . '/' . $shadowed,
            sprintf(
                '%s is mapped to the PHP class %s, and defining it here makes the '
                . 'class unreachable by that name in namespace %s (at %s:%d). '
                . 'Reach the class as \\%s, or rename the definition.',
                $shadowed,
                $shadowed,
                $ns,
                $location->getFile(),
                $location->getLine(),
                $shadowed,
            ),
        );
    }
}
