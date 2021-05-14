<?php

declare(strict_types=1);

namespace Phel\Compiler\Evaluator;

use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Throwable;

final class RequireEvaluator implements EvaluatorInterface
{
    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return mixed
     */
    public function eval(string $code)
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
