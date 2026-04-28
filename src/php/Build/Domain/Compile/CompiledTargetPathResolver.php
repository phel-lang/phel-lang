<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Shared\Facade\CompilerFacadeInterface;
use RuntimeException;

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
 * Phel behaviour — `phel\core` → `phel/core.php`). Secondary `(in-ns X)`
 * files are mapped from the source file path relative to the matching
 * source root, so sub-files ride along under the same directory as their
 * primary (`src/phel/core/util.phel` → `phel/core/util.php`).
 */
final readonly class CompiledTargetPathResolver
{
    private const string TARGET_FILE_EXTENSION = '.php';

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @param list<string> $sourceDirectories
     */
    public function resolve(NamespaceInformation $info, array $sourceDirectories): string
    {
        if ($info->isPrimaryDefinition()) {
            return $this->fromNamespace($info->getNamespace());
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
