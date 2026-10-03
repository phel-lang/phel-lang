<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use ParseError;
use Phel\Compiler\Infrastructure\Service\DebugLineTap;
use Phel\Shared\Exceptions\CompiledCodeIsMalformedException;
use Phel\Shared\Exceptions\FileException;
use Phel\Shared\Facade\FilesystemFacadeInterface;

use function getmypid;
use function md5;
use function md5_file;
use function sprintf;

/**
 * @internal
 */
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
    ) {}

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
        } catch (ParseError $parseError) {
            // Parse errors indicate malformed generated PHP code
            throw CompiledCodeIsMalformedException::fromThrowable($parseError);
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
     * The temp dir is shared by every process on the machine, and the write is
     * not atomic, while each process deletes its files at exit. Two processes
     * evaluating the same code under one name could read each other's empty,
     * half-written or deleted file: requiring an empty one defines nothing, so
     * a registered var reads as nil (#3495). The process id keeps their files
     * apart.
     */
    private function buildFilename(string $phpCode): string
    {
        return sprintf(
            '%s%s%s_%d_%s%s',
            $this->filesystemFacade->getTempDir(),
            DIRECTORY_SEPARATOR,
            self::TEMP_PREFIX,
            getmypid(),
            md5($phpCode),
            self::FILE_EXTENSION,
        );
    }

    /**
     * Writes the PHP code to file and registers it for cleanup.
     *
     * Nothing pre-compiles the file into OPcache: the caller `require`s it on
     * the very next line, and the file is deleted at the end of the run, so a
     * `.bin` for it could only ever become unreachable weight in the file
     * cache (#3268).
     *
     * @throws FileException
     */
    private function writeFile(string $filename, string $phpCode): void
    {
        if (file_put_contents($filename, $phpCode) === false) {
            throw FileException::canNotCreateFile($filename);
        }

        $this->filesystemFacade->addFile($filename);
    }
}
