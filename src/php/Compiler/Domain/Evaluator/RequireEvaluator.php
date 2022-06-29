<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Throwable;

final class RequireEvaluator implements EvaluatorInterface
{
    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function eval(string $code): mixed
    {
        $filename = tempnam(sys_get_temp_dir(), '__phel');
        if (!$filename) {
            throw FileException::canNotCreateTempFile();
        }

        try {
            file_put_contents($filename, "<?php\n" . $code);
            if (file_exists($filename)) {
                return require $filename;
            }

            throw FileException::canNotCreateFile($filename);
        } catch (Throwable $e) {
            throw CompiledCodeIsMalformedException::fromThrowable($e);
        }
    }
}
