<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Run\Application\Test\WorkerFrame;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function addslashes;
use function fclose;
use function fopen;
use function fwrite;
use function is_array;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function sprintf;

/**
 * Hidden subcommand: parallel-test worker. One process per pool slot,
 * lives for the whole test run, processes one namespace per work frame.
 *
 * Wire format: {@see WorkerFrame} (length-prefixed JSON).
 *
 *   parent -> worker (one per namespace):
 *     {"index": 17, "ns": "phel.http.test",
 *      "file": "/abs/path/...", "options": "{:filter nil ...}"}
 *
 *   worker -> parent (one per work frame):
 *     {"index": 17, "ns": "...", "ok": true,
 *      "output": "...captured stdout...",
 *      "failed-tests": [...], "error": null}
 *
 * Worker exits 0 when stdin closes (parent closes the pipe on shutdown).
 *
 * @method RunFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
final class TestWorkerCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const string COMMAND_NAME = '_test-worker';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Internal: parallel test worker. Not for direct use.')
            ->setHidden(true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stdin = fopen('php://stdin', 'rb');
        $stdout = fopen('php://stdout', 'wb');
        if ($stdin === false || $stdout === false) {
            return self::FAILURE;
        }

        try {
            // Bootstrap: load phel.core and every bundled phel.* module
            // so `(phel\test/run-tests ...)` resolves without per-call requires.
            $this->getFacade()->loadPhelNamespaces();

            while (true) {
                $frame = WorkerFrame::readBlocking($stdin);
                if ($frame === null) {
                    return self::SUCCESS;
                }

                $response = $this->handleWork($frame);
                fwrite($stdout, WorkerFrame::encode($response));
            }
        } finally {
            @fclose($stdin);
            @fclose($stdout);
        }
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return array<string, mixed>
     */
    private function handleWork(array $frame): array
    {
        $ns = (string) ($frame['ns'] ?? '');
        $index = (int) ($frame['index'] ?? -1);
        $file = (string) ($frame['file'] ?? '');
        $options = (string) ($frame['options'] ?? '{}');

        $base = [
            'type' => 'result',
            'index' => $index,
            'ns' => $ns,
        ];

        ob_start();
        try {
            foreach ($this->getFacade()->getDependenciesFromPaths([$file]) as $info) {
                $this->getFacade()->evalFile($info);
            }

            $phelCode = sprintf(
                "(do (phel\\test/run-tests %s '%s) "
                . '(phel\\json/encode {"ok" (phel\\test/successful?) '
                . '"failed-tests" (phel\\test/get-failed-tests)}))',
                $options,
                $ns,
            );

            $resultJson = $this->getFacade()->eval(
                $phelCode,
                new CompileOptions()->setIsEnabledSourceMaps(false),
            );

            $captured = (string) ob_get_clean();

            $parsed = is_string($resultJson) ? json_decode($resultJson, true) : null;
            $ok = is_array($parsed) && (bool) ($parsed['ok'] ?? false);
            /** @var list<string> $failedTests */
            $failedTests = [];
            if (is_array($parsed) && isset($parsed['failed-tests']) && is_array($parsed['failed-tests'])) {
                foreach ($parsed['failed-tests'] as $name) {
                    if (is_string($name)) {
                        $failedTests[] = $name;
                    }
                }
            }

            return $base + [
                'ok' => $ok,
                'output' => $captured,
                'failed-tests' => $failedTests,
                'error' => null,
            ];
        } catch (Throwable $throwable) {
            $captured = ob_get_clean();
            if (!is_string($captured)) {
                $captured = '';
            }

            $message = sprintf('<error>Failed running %s: %s</error>', $ns, $throwable->getMessage());

            return $base + [
                'ok' => false,
                'output' => $captured . "\n" . $message . "\n",
                'failed-tests' => [],
                'error' => addslashes($throwable->getMessage()),
            ];
        }
    }
}
