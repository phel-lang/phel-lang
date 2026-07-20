<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Gacela;
use Override;
use Phel\Run\Infrastructure\Command\ReplCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\Console\Input\InputInterface;

use function array_filter;
use function array_values;
use function str_starts_with;

final class ReplDefaultTapTest extends AbstractTestCommand
{
    use ReplCommandTestTrait;

    private string $previousCwd = '';

    private string $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCwd = getcwd() ?: '';
        $this->tempDir = $this->containerTempDir();
        chdir($this->tempDir);
        Gacela::bootstrap($this->tempDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        $this->cleanupContainerTempDirs();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_tap_output_is_visible_by_default(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(tap> :hello)'),
            new InputLine('user:2> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        // print-tap writes `tap> <value>` via php/print; ReplTestIo drains that
        // stdout into its transcript. The tap line starts with `tap> `, unlike
        // the echoed input prompt `user:1> (tap> :hello)`, so filter on that
        // prefix (a bare `tap> `/`:hello` substring would also hit the echo).
        // println-colorful wraps the value in ANSI codes, so match a substring.
        $tapLines = array_values($this->tapLines($io));
        self::assertCount(1, $tapLines, 'default tap must print exactly one "tap> " line');
        self::assertStringContainsString(':hello', $tapLines[0], 'the tap line must carry the tapped value');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_default_tap_can_be_removed(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(remove-tap phel.repl/print-tap)'),
            new InputLine('user:2> ', '(tap> :silent)'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        // No `tap> ...` line after removal. The echoed input prompt
        // `user:2> (tap> :silent)` starts with `user:`, not `tap> `, so the
        // prefix filter distinguishes them.
        self::assertSame([], array_values($this->tapLines($io)), 'removed tap must not print a "tap> " line');
    }

    /**
     * Output lines the default tap handler produced: those starting with
     * `tap> `. Excludes echoed input prompts like `user:2> (tap> :x)`.
     *
     * @return list<string>
     */
    private function tapLines(ReplTestIo $io): array
    {
        return array_values(array_filter(
            $io->getOutputLines(),
            static fn(string $line): bool => str_starts_with($line, 'tap> '),
        ));
    }
}
