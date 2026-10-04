<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\NamespaceInformation;
use RuntimeException;

use function dirname;
use function explode;
use function implode;
use function ltrim;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Resolves the destination relative path for a Phel source file inside the
 * build output directory.
 *
 * Primary `(ns X)` files are mapped from the namespace (matching classic
 * Phel behaviour — `phel\core` → `phel/core.php`). A secondary `(in-ns X)`
 * file sits where its built primary looks for it: the emitted `(load ...)`
 * probes the primary's own directory with a key relative to the primary's
 * source file. So `src/main_extra.phel`, loaded by `app.main` from
 * `src/main.phel`, goes to `app/main_extra.php`, next to `app/main.php`.
 * Without a primary, or for a file outside the primary's directory, the
 * secondary keeps its path relative to the source root.
 *
 * @internal
 */
final readonly class CompiledTargetPathResolver
{
    private const string TARGET_FILE_EXTENSION = '.php';

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @param list<string> $sourceDirectories
     * @param string|null  $primaryFile       source file of the `(ns X)` a secondary belongs to
     */
    public function resolve(NamespaceInformation $info, array $sourceDirectories, ?string $primaryFile = null): string
    {
        if ($info->isPrimaryDefinition()) {
            return $this->fromNamespace($info->getNamespace());
        }

        if ($primaryFile !== null) {
            $primaryDir = dirname($primaryFile) . DIRECTORY_SEPARATOR;
            if (str_starts_with($info->getFile(), $primaryDir)) {
                $targetDir = dirname($this->fromNamespace($info->getNamespace()));
                $relative = $this->toCompiledPath(substr($info->getFile(), strlen($primaryDir)));

                return $targetDir === '.' ? $relative : $targetDir . DIRECTORY_SEPARATOR . $relative;
            }
        }

        return $this->fromSourceFile($info->getFile(), $sourceDirectories);
    }

    private function fromNamespace(string $namespace): string
    {
        $munged = $this->compilerFacade->encodeNs($namespace);

        return implode(DIRECTORY_SEPARATOR, explode('\\', $munged)) . self::TARGET_FILE_EXTENSION;
    }

    /**
     * @param list<string> $sourceDirectories
     */
    private function fromSourceFile(string $file, array $sourceDirectories): string
    {
        foreach ($sourceDirectories as $directory) {
            $prefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($file, $prefix)) {
                $relative = substr($file, strlen($prefix));

                return $this->toCompiledPath($relative);
            }
        }

        throw new RuntimeException(sprintf(
            'Cannot determine output path for secondary (in-ns ...) file "%s" — it does not live under any configured source directory.',
            $file,
        ));
    }

    private function toCompiledPath(string $relativeSourcePath): string
    {
        $normalized = str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativeSourcePath, '/'));

        return (string) preg_replace('/\.(phel|cljc)$/i', self::TARGET_FILE_EXTENSION, $normalized);
    }
}
