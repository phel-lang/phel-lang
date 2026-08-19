<?php

declare(strict_types=1);

namespace PhelTest\Unit\Balance\Application;

use Gacela\Framework\Gacela;
use Phel\Balance\Application\BoundaryRepairer;
use Phel\Balance\Application\DelimiterRepairer;
use Phel\Balance\Application\DelimiterScanner;
use Phel\Balance\Application\PathsBalancer;
use Phel\Balance\Application\RepairValidator;
use Phel\Balance\Application\UnexpectedCloserRepairer;
use Phel\Balance\Domain\BalanceOutcome;
use Phel\Balance\Domain\Exception\BalanceSourceException;
use Phel\Balance\Domain\FileCollectorInterface;
use Phel\Balance\Domain\FileIoInterface;
use Phel\Balance\Domain\RepairStrategy;
use Phel\Compiler\CompilerFacade;
use PHPUnit\Framework\TestCase;

final class PathsBalancerTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_it_leaves_a_balanced_file_untouched(): void
    {
        $io = $this->fileIo(['ok.phel' => "(str x)\n"]);

        $result = $this->balancer($io)->balance(['ignored'], true);

        self::assertSame(BalanceOutcome::Balanced, $result->outcomes[0]->outcome);
        self::assertSame([], $io->written);
    }

    public function test_it_reports_without_writing_when_fix_is_off(): void
    {
        $io = $this->fileIo(['broken.phel' => "(str x\n"]);

        $result = $this->balancer($io)->balance(['ignored'], false);

        self::assertSame(BalanceOutcome::NeedsRepair, $result->outcomes[0]->outcome);
        self::assertSame([], $io->written, 'detection must never write');
    }

    public function test_it_writes_the_repaired_source_when_fix_is_on(): void
    {
        $io = $this->fileIo(['broken.phel' => "(str x\n"]);

        $result = $this->balancer($io)->balance(['ignored'], true);

        self::assertSame(BalanceOutcome::Repaired, $result->outcomes[0]->outcome);
        self::assertSame(['broken.phel' => "(str x)\n"], $io->written);
    }

    /**
     * An unterminated `#"regex"`, a bare `#` and the removed `#| |#` block
     * comment fail to lex rather than lexing to something countable.
     */
    public function test_it_reports_a_file_that_cannot_be_lexed_as_unrepairable(): void
    {
        $io = $this->fileIo(['bad.phel' => "(re #\"abc\n"]);

        $result = $this->balancer($io)->balance(['ignored'], true);

        self::assertSame(BalanceOutcome::Unrepairable, $result->outcomes[0]->outcome);
        self::assertSame([], $io->written);
    }

    public function test_it_reports_an_unreadable_file_as_unrepairable(): void
    {
        $io = $this->fileIo([]);

        $result = $this->balancer($io, ['missing.phel'])->balance(['ignored'], true);

        self::assertSame(BalanceOutcome::Unrepairable, $result->outcomes[0]->outcome);
        self::assertStringContainsString('Cannot read file', (string) $result->outcomes[0]->reason);
    }

    public function test_boundary_strategy_inserts_closers_before_the_next_definition(): void
    {
        $source = "(defn first-value []\n  (let [value 1]\n    (+ value 1))\n\n(defn second-value [] 2)\n";
        $io = $this->fileIo(['broken.phel' => $source]);

        $result = $this->balancer($io)->balance(['ignored'], true, RepairStrategy::Boundary);

        self::assertSame(BalanceOutcome::Repaired, $result->outcomes[0]->outcome);
        self::assertSame("(defn first-value []\n  (let [value 1]\n    (+ value 1)))\n\n(defn second-value [] 2)\n", $io->written['broken.phel']);
    }

    public function test_delete_strategy_refuses_a_mismatched_inner_closer(): void
    {
        $source = "(defn first-value []\n  (let [items [1 2]]\n    items]\n)\n";
        $io = $this->fileIo(['broken.phel' => $source]);

        $result = $this->balancer($io)->balance(['ignored'], true, RepairStrategy::DeleteUnexpected);

        self::assertSame(BalanceOutcome::Unrepairable, $result->outcomes[0]->outcome);
        self::assertSame([], $io->written);
    }

    public function test_boundary_strategy_preserves_crlf_line_endings(): void
    {
        $source = "(defn first-value []\r\n  1\r\n\r\n(defn second-value [] 2)\r\n";
        $io = $this->fileIo(['broken.phel' => $source]);

        $result = $this->balancer($io)->balance(['ignored'], true, RepairStrategy::Boundary);

        self::assertSame(BalanceOutcome::Repaired, $result->outcomes[0]->outcome);
        self::assertSame("(defn first-value []\r\n  1)\r\n\r\n(defn second-value [] 2)\r\n", $io->written['broken.phel']);
    }

    public function test_delete_strategy_still_refuses_an_ambiguous_wrong_closer(): void
    {
        $io = $this->fileIo(['broken.phel' => "(foo]\n"]);

        $result = $this->balancer($io)->balance(['ignored'], true, RepairStrategy::DeleteUnexpected);

        self::assertSame(BalanceOutcome::Unrepairable, $result->outcomes[0]->outcome);
        self::assertSame([], $io->written);
    }

    public function test_delete_strategy_does_not_report_a_mismatch_as_needing_repair_without_fix(): void
    {
        $io = $this->fileIo(['broken.phel' => "(foo]\n"]);

        $result = $this->balancer($io)->balance(['ignored'], false, RepairStrategy::DeleteUnexpected);

        self::assertSame(BalanceOutcome::Unrepairable, $result->outcomes[0]->outcome);
        self::assertSame([], $io->written);
    }

    public function test_one_unrepairable_file_does_not_stop_the_batch(): void
    {
        $io = $this->fileIo([
            'a.phel' => "(foo]\n",
            'b.phel' => "(str x\n",
        ]);

        $result = $this->balancer($io)->balance(['ignored'], true);

        self::assertSame(BalanceOutcome::Unrepairable, $result->outcomes[0]->outcome);
        self::assertSame(BalanceOutcome::Repaired, $result->outcomes[1]->outcome);
        self::assertSame(['b.phel' => "(str x)\n"], $io->written);
    }

    /**
     * @param list<string>|null $collected
     */
    private function balancer(FileIoInterface $io, ?array $collected = null): PathsBalancer
    {
        return new PathsBalancer(
            $this->fileCollector($collected ?? $this->pathsOf($io)),
            new DelimiterScanner(new CompilerFacade()),
            new DelimiterRepairer(),
            $io,
            new BoundaryRepairer(),
            new UnexpectedCloserRepairer(),
            new RepairValidator(new CompilerFacade(), new DelimiterScanner(new CompilerFacade())),
        );
    }

    /**
     * @return list<string>
     */
    private function pathsOf(FileIoInterface $io): array
    {
        /** @var object{files: array<string, string>} $io */
        return array_keys($io->files);
    }

    /**
     * @param array<string, string> $files
     */
    private function fileIo(array $files): FileIoInterface
    {
        return new class($files) implements FileIoInterface {
            /** @var array<string, string> */
            public array $written = [];

            /**
             * @param array<string, string> $files
             */
            public function __construct(public array $files) {}

            public function read(string $path): string
            {
                return $this->files[$path] ?? throw BalanceSourceException::cannotRead($path);
            }

            public function write(string $path, string $contents): void
            {
                $this->written[$path] = $contents;
                $this->files[$path] = $contents;
            }
        };
    }

    /**
     * @param list<string> $paths
     */
    private function fileCollector(array $paths): FileCollectorInterface
    {
        return new readonly class($paths) implements FileCollectorInterface {
            /**
             * @param list<string> $paths
             */
            public function __construct(private array $paths) {}

            public function collect(array $paths): array
            {
                return $this->paths;
            }
        };
    }
}
