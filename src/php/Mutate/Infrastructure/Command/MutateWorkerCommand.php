<?php

declare(strict_types=1);

namespace Phel\Mutate\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Mutate\Application\MutantWorkerSession;
use Phel\Mutate\Application\MutationRunner;
use Phel\Mutate\MutateConfig;
use Phel\Mutate\MutateFacade;
use Phel\Mutate\MutateFactory;
use Phel\Shared\Process\WorkerFrame;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function fclose;
use function fopen;
use function fwrite;
use function is_array;
use function is_string;

/**
 * Hidden subcommand: the `phel mutate` worker. Lives for the whole run and
 * answers one frame at a time over stdin/stdout ({@see WorkerFrame}):
 *
 *   {"type": "load", "files": [...], "tests": [...]}   -> {"ok": true}
 *   {"type": "baseline"}                                 -> {"ok": true, "passed": bool, "total": int, "seconds": float}
 *   {"type": "mutant", "ns", "code", "restore"}          -> {"ok": true, "verdict", "seconds", "detail"}
 *
 * A mutant that never terminates or crashes the interpreter takes the
 * worker with it; the parent notices and starts a fresh one.
 *
 * @method MutateFacade  getFacade()
 * @method MutateFactory getFactory()
 * @method MutateConfig  getConfig()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: MutateFacade::class)]
#[ServiceMap(method: 'getFactory', className: MutateFactory::class)]
#[ServiceMap(method: 'getConfig', className: MutateConfig::class)]
final class MutateWorkerCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const string COMMAND_NAME = '_mutate-worker';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Internal: mutation-testing worker. Not for direct use.')
            ->setHidden(true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stdin = fopen('php://stdin', 'rb');
        $stdout = fopen('php://stdout', 'wb');
        if ($stdin === false || $stdout === false) {
            return self::FAILURE;
        }

        $session = $this->getFacade()->createWorkerSession();
        try {
            while (true) {
                $frame = WorkerFrame::readBlocking($stdin);
                if ($frame === null) {
                    return self::SUCCESS;
                }

                fwrite($stdout, WorkerFrame::encode($this->answer($session, $frame)));
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
    private function answer(MutantWorkerSession $session, array $frame): array
    {
        try {
            return match (ScalarCoercion::toString($frame['type'] ?? null)) {
                MutationRunner::TYPE_LOAD => $this->load($session, $frame),
                MutationRunner::TYPE_BASELINE => ['ok' => true] + $session->baseline(),
                MutationRunner::TYPE_COVERAGE => $this->adoptCoverage($session, $frame),
                MutationRunner::TYPE_MUTANT => ['ok' => true] + $session->mutant(
                    ScalarCoercion::toString($frame['ns'] ?? null),
                    ScalarCoercion::toString($frame['code'] ?? null),
                    ScalarCoercion::toString($frame['restore'] ?? null),
                    ScalarCoercion::toString($frame['file'] ?? null),
                    $this->lineRange($frame['lines'] ?? null),
                ),
                default => ['ok' => false, 'error' => 'unknown frame type'],
            };
        } catch (Throwable $throwable) {
            return ['ok' => false, 'error' => $throwable->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return array<string, mixed>
     */
    private function adoptCoverage(MutantWorkerSession $session, array $frame): array
    {
        $raw = $frame['testsByLine'] ?? null;
        $testsByLine = [];
        if (is_array($raw)) {
            foreach ($raw as $file => $lines) {
                if (!is_string($file)) {
                    continue;
                }

                if (!is_array($lines)) {
                    continue;
                }

                foreach ($lines as $line => $tests) {
                    $testsByLine[$file][(int) $line] = ScalarCoercion::toStringList($tests);
                }
            }
        }

        $session->adoptCoverage($testsByLine);

        return ['ok' => true];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function lineRange(mixed $raw): ?array
    {
        if (!is_array($raw) || !isset($raw[0], $raw[1])) {
            return null;
        }

        return [ScalarCoercion::toInt($raw[0]), ScalarCoercion::toInt($raw[1])];
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return array<string, mixed>
     */
    private function load(MutantWorkerSession $session, array $frame): array
    {
        $session->load(
            ScalarCoercion::toStringList($frame['files'] ?? null),
            ScalarCoercion::toStringList($frame['tests'] ?? null),
        );

        return ['ok' => true];
    }
}
