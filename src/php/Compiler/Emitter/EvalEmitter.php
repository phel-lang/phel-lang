<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use RuntimeException;
use Throwable;

final class EvalEmitter
{
    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @return mixed
     *
     * @throws RuntimeException|Throwable
     */
    public function eval(string $code)
    {
        $filename = tempnam(sys_get_temp_dir(), '__phel');
        if (!$filename) {
            throw new RuntimeException('can not create temp file.');
        }

        try {
            file_put_contents($filename, "<?php\n" . $code);
            if (file_exists($filename)) {
                return require $filename;
            }

            throw new RuntimeException('Can not require file: ' . $filename);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
