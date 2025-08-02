<?php

declare(strict_types=1);

namespace PhelTest\Expect;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function sprintf;

class ExpectTest extends TestCase
{
    public function test_repl_require(): void
    {
        $process = new Process(['./tests/php/Expect/repl_require.exp']);
        $process->run();

        if ($process->getExitCode() !== 0) {
            $message = sprintf('Process failed with exit code %s.', $process->getExitCode());

            $stdout = $process->getOutput();
            if ($stdout !== '' && $stdout !== '0') {
                $message .= '
STDOUT:
' . $stdout;
            }

            $stderr = $process->getErrorOutput();
            if ($stderr !== '' && $stderr !== '0') {
                $message .= '
STDERR:
' . $stderr;
            }

            $this->fail($message);
        }

        $this->assertSame(0, $process->getExitCode());
    }
}
