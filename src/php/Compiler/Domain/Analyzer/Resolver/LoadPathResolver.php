<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Resolver;

use InvalidArgumentException;
use Phel\Compiler\Application\Munge;

use function explode;
use function implode;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * Resolves a `(load "...")` path argument into a portable, classpath-
 * oriented description that the emitter uses to generate a runtime
 * lookup.
 *
 * A leading slash marks a classpath-absolute path; anything else is
 * resolved relative to the caller's namespace directory on the
 * classpath. The resolver never bakes absolute filesystem paths from
 * the compiling machine — that would break distribution.
 */
final readonly class LoadPathResolver
{
    private const string EXTENSION = '.phel';

    public function __construct(
        private Munge $munge = new Munge(),
    ) {}

    public function resolve(?string $callerNamespace, string $pathArg): LoadPathResolution
    {
        $this->rejectInvalidPathArg($pathArg);

        if ($this->isClasspathAbsolute($pathArg)) {
            return LoadPathResolution::classpathAbsolute(substr($pathArg, 1));
        }

        if ($callerNamespace === null || $callerNamespace === '') {
            throw new InvalidArgumentException(sprintf(
                "'load cannot resolve relative path '%s' — no caller namespace available",
                $pathArg,
            ));
        }

        return LoadPathResolution::callerRelative($pathArg, $this->classpathDirOf($callerNamespace));
    }

    /**
     * The directory component of a namespace when laid out on the
     * classpath. `phel\core` → `phel`; `loade2e\core` → `loade2e`;
     * top-level namespaces like `user` produce an empty string.
     */
    private function classpathDirOf(string $namespace): string
    {
        $munged = $this->munge->encodeNs($namespace);
        $parts = explode('\\', $munged);
        array_pop($parts);

        return implode('/', $parts);
    }

    private function rejectInvalidPathArg(string $pathArg): void
    {
        if ($pathArg === '') {
            throw new InvalidArgumentException("'load path must not be empty");
        }

        if (str_ends_with($pathArg, self::EXTENSION)) {
            throw new InvalidArgumentException(sprintf(
                "'load path must not include the '%s' extension, got: %s",
                self::EXTENSION,
                $pathArg,
            ));
        }

        if (str_starts_with($pathArg, './') || str_starts_with($pathArg, '../')) {
            throw new InvalidArgumentException(sprintf(
                "'load path must not start with './' or '../'; use a relative or classpath-absolute path, got: %s",
                $pathArg,
            ));
        }
    }

    private function isClasspathAbsolute(string $pathArg): bool
    {
        return str_starts_with($pathArg, '/');
    }
}
