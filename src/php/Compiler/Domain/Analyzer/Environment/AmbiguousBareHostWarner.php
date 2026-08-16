<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\PhpClassLike;
use Phel\Compiler\Domain\Diagnostic\CompilerWarnings;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function sprintf;

/**
 * Detects a bare all-caps name in value position that also names a loadable
 * PHP class.
 *
 * Value position reads such a name as the global constant, always, so that the
 * emitted PHP stops depending on what the compiling process had autoloaded
 * (#3064). Where a class of the same name exists, that rule silently picks the
 * other meaning than the one this code used to get, which is precisely the case
 * {@see CompilerWarnings} exists for: the diagnostic has already changed what
 * the program does.
 *
 * The class probe survives only here. It makes the *warning* depend on the
 * environment, which is harmless — a missed warning, never a different
 * compilation — while the resolution it comments on stays lexical.
 *
 * Class position is not warned about: a member target, a constructor and a
 * callable all say "class" lexically, and {@see BareHostClass} reads them that
 * way whether or not the class can be loaded.
 *
 * Detection only: the stdlib suppression and the per-`(file, subject)` dedup
 * belong to {@see CompilerWarnings}, which is why this class is stateless.
 *
 * @internal
 */
final class AmbiguousBareHostWarner
{
    private static ?self $instance = null;

    /**
     * Shared instance. Stateless, so it exists only to spare the analyzer a
     * per-resolution allocation; there is nothing to reset between runs.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function maybeWarn(Symbol $name): void
    {
        $location = $name->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        $strName = $name->getName();
        if (!PhpClassLike::exists($strName)) {
            return;
        }

        CompilerWarnings::warnOnceForSource(
            $location->getFile(),
            'bare-all-caps/' . $strName,
            sprintf(
                '%s reads as the global constant %s here, but it is also a PHP class '
                . '(at %s:%d). Spell the class as \\%s, import it with (:use %s), or write '
                . '%s/class; php/%s is the constant, explicitly.',
                $strName,
                $strName,
                $location->getFile(),
                $location->getLine(),
                $strName,
                $strName,
                $strName,
                $strName,
            ),
        );
    }
}
