<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Evaluator;

use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\TrarnspiledCodeIsMalformedException;
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
     * @throws TrarnspiledCodeIsMalformedException
     * @throws FileException
     */
    public function eval(string $code): mixed
    {
        $filename = tempnam(sys_get_temp_dir(), '__phel');
        if ($filename === '' || $filename === false) {
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
            throw TrarnspiledCodeIsMalformedException::fromThrowable($throwable);
        }
    }
}
