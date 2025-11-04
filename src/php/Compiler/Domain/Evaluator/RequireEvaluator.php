<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Run\Infrastructure\Service\DebugLineTap;
use Throwable;

use function function_exists;
use function md5;
use function md5_file;
use function sprintf;

final class RequireEvaluator implements EvaluatorInterface
{
    private const string TEMP_PREFIX = '__phel';

    private const string FILE_EXTENSION = '.php';

    /**
     * Process-local cache for compiled code.
     * Maps MD5 hash of PHP code to the evaluated result.
     *
     * @var array<string, mixed>
     */
    private static array $processCache = [];

    public function __construct(
        private readonly FilesystemFacadeInterface $filesystemFacade,
    ) {
    }

    /**
     * Clears the process-local memory cache.
     * Useful for testing or resetting state between evaluations.
     */
    public static function clearCache(): void
    {
        self::$processCache = [];
    }

    /**
     * Evaluates the code and returns the evaluated value.
     *
     * Uses a two-layer caching strategy:
     * 1. Process-local memory cache (fastest, no file I/O)
     * 2. File cache with content verification (protects against TOCTTOU attacks)
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function eval(string $code): mixed
    {
        $phpCode = $this->buildPhpCode($code);
        $hash = md5($phpCode);

        // Layer 1: Check process-local memory cache
        if (isset(self::$processCache[$hash])) {
            return self::$processCache[$hash];
        }

        // Layer 2: File cache with content verification (TOCTTOU protection)
        $filename = $this->buildFilename($phpCode);

        try {
            // Verify file exists and content matches before requiring
            if (!file_exists($filename) || md5_file($filename) !== $hash) {
                $this->writeFile($filename, $phpCode);
            }

            $result = require $filename;

            // Cache result in process-local memory for subsequent calls
            self::$processCache[$hash] = $result;

            return $result;
        } catch (Throwable $throwable) {
            throw CompiledCodeIsMalformedException::fromThrowable($throwable);
        }
    }

    /**
     * Builds the PHP code with optional debug declarations.
     */
    private function buildPhpCode(string $code): string
    {
        return DebugLineTap::isEnabled()
            ? "<?php\ndeclare(ticks=1);\n" . $code
            : "<?php\n" . $code;
    }

    /**
     * Builds a deterministic filename based on the MD5 hash of the code.
     */
    private function buildFilename(string $phpCode): string
    {
        return sprintf(
            '%s%s%s_%s%s',
            $this->filesystemFacade->getTempDir(),
            DIRECTORY_SEPARATOR,
            self::TEMP_PREFIX,
            md5($phpCode),
            self::FILE_EXTENSION,
        );
    }

    /**
     * Writes the PHP code to file, registers it, and compiles if possible.
     *
     * @throws FileException
     */
    private function writeFile(string $filename, string $phpCode): void
    {
        if (file_put_contents($filename, $phpCode) === false) {
            throw FileException::canNotCreateFile($filename);
        }

        $this->filesystemFacade->addFile($filename);

        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($filename);
        }
    }
}
