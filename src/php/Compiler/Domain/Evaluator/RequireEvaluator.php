<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Run\Infrastructure\Service\DebugLineTap;
use Throwable;

use function function_exists;
use function sprintf;

final readonly class RequireEvaluator implements EvaluatorInterface
{
    private const string TEMP_PREFIX = '__phel';

    private const string FILE_EXTENSION = '.php';

    public function __construct(
        private FilesystemFacadeInterface $filesystemFacade,
    ) {
    }

    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function eval(string $code): mixed
    {
        $phpCode = $this->buildPhpCode($code);
        $filename = $this->buildFilename($phpCode);

        try {
            if (!file_exists($filename)) {
                $this->writeFile($filename, $phpCode);
            }

            return require $filename;
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
