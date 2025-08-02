<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Filesystem\FilesystemFacadeInterface;
use Throwable;

use function function_exists;

final readonly class RequireEvaluator implements EvaluatorInterface
{
    private const string TEMP_PREFIX = '__phel';

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
        // Suppress possible notice when PHP falls back to the system temp directory
        $filename = @tempnam($this->filesystemFacade->getTempDir(), self::TEMP_PREFIX);
        if ($filename === false) {
            throw FileException::canNotCreateTempFile();
        }

        $this->filesystemFacade->addFile($filename);

        try {
            file_put_contents($filename, "<?php\n" . $code);
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
}
