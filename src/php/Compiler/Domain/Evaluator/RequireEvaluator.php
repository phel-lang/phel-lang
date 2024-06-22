<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Filesystem\FilesystemFacadeInterface;
use Throwable;

final readonly class RequireEvaluator implements EvaluatorInterface
{
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
        $filename = tempnam(sys_get_temp_dir(), '__phel');
        if ($filename === false) {
            throw FileException::canNotCreateTempFile();
        }

        $this->filesystemFacade->addFile($filename);

        try {
            file_put_contents($filename, "<?php\n" . $code);
            if (file_exists($filename)) {
                return require $filename;
            }

            throw FileException::canNotCreateFile($filename);
        } catch (Throwable $throwable) {
            throw CompiledCodeIsMalformedException::fromThrowable($throwable);
        }
    }
}
