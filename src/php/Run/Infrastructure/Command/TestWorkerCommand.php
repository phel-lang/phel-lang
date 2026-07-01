<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Lang\Registry;
use Phel\Run\Application\Test\FrameKey;
use Phel\Run\Application\Test\WorkerFrame;
use Phel\Run\Application\Test\WorkRequest;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function fclose;
use function fopen;
use function fwrite;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function sprintf;
use function str_replace;

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

    /** @var array<string, true> Dependency files already evaluated by this long-lived worker. */
    private array $preloadedFiles = [];

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

                fwrite($stdout, WorkerFrame::encode($this->handleWork($frame)));
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
        $request = WorkRequest::fromFrame($frame);

        ob_start();
        try {
            $this->preloadDependencies($request->file);
            $resultJson = $this->runTestsForNamespace($request);
            $captured = (string) ob_get_clean();

            return $request->baseResponse() + $this->parseResult($resultJson, $captured);
        } catch (Throwable $throwable) {
            $captured = (string) ob_get_clean();

            return $request->baseResponse() + [
                FrameKey::OK => false,
                FrameKey::OUTPUT => $captured . "\n"
                    . sprintf('<error>Failed running %s: %s</error>', $request->ns, $throwable->getMessage())
                    . "\n",
                FrameKey::FAILED_TESTS => [],
                FrameKey::ERROR => $throwable->getMessage(),
            ];
        }
    }

    private function preloadDependencies(string $file): void
    {
        if ($file === '') {
            return;
        }

        foreach ($this->getFacade()->getDependenciesFromPaths([$file]) as $info) {
            // The worker is long-lived: eval each dependency file once across all frames.
            $depFile = $info->getFile();
            if (isset($this->preloadedFiles[$depFile])) {
                continue;
            }

            // Skip namespaces already loaded at bootstrap (loadPhelNamespaces brings in
            // the whole bundled phel.* stdlib). Re-evaluating a precompiled sibling (PHAR)
            // re-runs its primary, which re-nulls forward-declared defs (map/seq/nil?)
            // that its require_once secondaries then won't restore — a null callable. (#2672)
            if (Registry::getInstance()->hasNamespace(str_replace('-', '_', $info->getNamespace()))) {
                $this->preloadedFiles[$depFile] = true;
                continue;
            }

            $this->getFacade()->evalFile($info);
            $this->preloadedFiles[$depFile] = true;
        }
    }

    private function runTestsForNamespace(WorkRequest $request): mixed
    {
        $phelCode = sprintf(
            "(do (phel\\test/run-tests %s '%s) "
            . '(phel\\json/encode {"ok" (phel\\test/successful?) '
            . '"failed-tests" (phel\\test/get-failed-tests) '
            . '"counts" (get (phel\\test/get-stats) :counts)}))',
            $request->options,
            $request->ns,
        );

        return $this->getFacade()->eval(
            $phelCode,
            new CompileOptions()->setIsEnabledSourceMaps(false),
        );
    }

    /**
     * @return array{ok: bool, output: string, failed-tests: list<string>, counts: array<string, int>, error: null}
     */
    private function parseResult(mixed $resultJson, string $captured): array
    {
        $parsed = is_string($resultJson) ? json_decode($resultJson, true) : null;
        $ok = is_array($parsed) && (bool) ($parsed['ok'] ?? false);

        return [
            FrameKey::OK => $ok,
            FrameKey::OUTPUT => $captured,
            FrameKey::FAILED_TESTS => $this->extractFailedTests($parsed),
            FrameKey::COUNTS => $this->extractCounts($parsed),
            FrameKey::ERROR => null,
        ];
    }

    /**
     * @return list<string>
     */
    private function extractFailedTests(mixed $parsed): array
    {
        if (!is_array($parsed) || !isset($parsed['failed-tests']) || !is_array($parsed['failed-tests'])) {
            return [];
        }

        $out = [];
        foreach ($parsed['failed-tests'] as $name) {
            if (is_string($name)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function extractCounts(mixed $parsed): array
    {
        if (!is_array($parsed) || !isset($parsed['counts']) || !is_array($parsed['counts'])) {
            return [];
        }

        $out = [];
        foreach ($parsed['counts'] as $key => $value) {
            if (is_string($key) && (is_int($value) || is_numeric($value))) {
                $out[$key] = (int) $value;
            }
        }

        return $out;
    }
}
