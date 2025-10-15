<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Run\Infrastructure\Service\DebugLineTap;
use Throwable;

use function function_exists;
use function sprintf;

final readonly class RequireEvaluator implements EvaluatorInterface
{
    public const string CACHE_PREFIX = '__phel';

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
    public function eval(string $code, CompileOptions $compileOptions = new CompileOptions()): mixed
    {
        $filename = $this->generateTempFileName($compileOptions);
        $this->filesystemFacade->addFile($filename);

        try {
            // Inject declare(ticks=1) if debug is enabled
            $phpCode = DebugLineTap::isEnabled()
                ? "<?php\ndeclare(ticks=1);\n" . $code
                : "<?php\n" . $code;

            file_put_contents($filename, $phpCode);
            if (file_exists($filename)) {
                if (function_exists('opcache_compile_file')) {
                    @opcache_compile_file($filename);
                }

                return require $filename;
            }

            throw FileException::canNotCreateFile($filename);
        } catch (Throwable $throwable) {
            throw CompiledCodeIsMalformedException::fromThrowable($throwable);
        }
    }

    private function generateTempFileName(CompileOptions $compileOptions): string
    {
        // Suppress possible notice when PHP falls back to the system temp directory
        $filename = @tempnam(
            $this->filesystemFacade->getTempDir(),
            sprintf(self::CACHE_PREFIX . '-%s-', $compileOptions->getSource()),
        );
        if ($filename === false) {
            throw FileException::canNotCreateTempFile();
        }

        return $filename;
    }
}
