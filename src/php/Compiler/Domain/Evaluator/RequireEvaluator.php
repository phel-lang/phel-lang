<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Run\Infrastructure\Service\DebugLineTap;
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
        // Inject declare(ticks=1) if debug is enabled
        $phpCode = DebugLineTap::isEnabled()
            ? "<?php\ndeclare(ticks=1);\n" . $code
            : "<?php\n" . $code;

        $filename = $this->filesystemFacade->getTempDir() . '/' . self::TEMP_PREFIX . '_' . md5($phpCode) . '.php';

        $this->filesystemFacade->addFile($filename);

        try {
            if (!file_exists($filename)) {
                file_put_contents($filename, $phpCode);
            }

            if (!file_exists($filename)) {
                throw FileException::canNotCreateFile($filename);
            }

            if (function_exists('opcache_compile_file')) {
                @opcache_compile_file($filename);
            }

            return require $filename;
        } catch (Throwable $throwable) {
            throw CompiledCodeIsMalformedException::fromThrowable($throwable);
        }
    }
}
