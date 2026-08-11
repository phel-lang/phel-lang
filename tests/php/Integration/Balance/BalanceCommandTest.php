<?php

declare(strict_types=1);

namespace PhelTest\Integration\Balance;

use Phel;
use Phel\Balance\Infrastructure\Command\BalanceCommand;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class BalanceCommandTest extends TestCase
{
    private string $workDir = '';

    protected function tearDown(): void
    {
        if ($this->workDir === '') {
            return;
        }

        foreach (glob($this->workDir . '/*.phel') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->workDir);
        $this->workDir = '';
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_exits_zero_on_a_balanced_file(): void
    {
        $this->bootstrap();
        $path = $this->writeFile('ok.phel', "(defn f [x] (str x))\n");

        $tester = new CommandTester(new BalanceCommand());
        $exit = $tester->execute(['paths' => [$path]]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('all balanced', $tester->getDisplay());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reports_an_imbalance_without_writing_when_fix_is_absent(): void
    {
        $this->bootstrap();
        $source = "(defn f [x]\n  (str x\n";
        $path = $this->writeFile('broken.phel', $source);

        $tester = new CommandTester(new BalanceCommand());
        $exit = $tester->execute(['paths' => [$path]]);

        self::assertSame(1, $exit);
        self::assertStringContainsString("unclosed '('", $tester->getDisplay());
        self::assertStringContainsString('--fix', $tester->getDisplay());
        self::assertSame($source, file_get_contents($path), 'detection must not rewrite the file');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_repairs_and_exits_zero_with_fix(): void
    {
        $this->bootstrap();
        $path = $this->writeFile('fixme.phel', "(defn f [x]\n  (str x\n");

        $tester = new CommandTester(new BalanceCommand());
        $exit = $tester->execute(['paths' => [$path], '--fix' => true]);

        self::assertSame(0, $exit, 'a run that repaired everything must not fail an agent hook');
        self::assertSame("(defn f [x]\n  (str x))\n", file_get_contents($path));
    }

    /**
     * The lexer reads the `)` the author meant as string content as a real
     * closer, so the imbalance is a phantom and appending to it writes a
     * differently broken file.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_refuses_to_repair_an_unterminated_string(): void
    {
        $this->bootstrap();
        $source = "(println \"hi) (there\n";
        $path = $this->writeFile('unterminated.phel', $source);

        $tester = new CommandTester(new BalanceCommand());
        $exit = $tester->execute(['paths' => [$path], '--fix' => true]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('unterminated string literal', $tester->getDisplay());
        self::assertSame($source, file_get_contents($path));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_refuses_to_repair_a_mismatched_closer(): void
    {
        $this->bootstrap();
        $source = "(defn bad [x]\n  (foo]\n";
        $path = $this->writeFile('mismatch.phel', $source);

        $tester = new CommandTester(new BalanceCommand());
        $exit = $tester->execute(['paths' => [$path], '--fix' => true]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Cannot repair automatically', $tester->getDisplay());
        self::assertSame($source, file_get_contents($path));
    }

    /**
     * The delimiters here only look unbalanced to a byte counter: `\(` is a
     * character literal, and the other two live inside a string and a comment.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_count_delimiters_in_char_literals_strings_or_comments(): void
    {
        $this->bootstrap();
        $path = $this->writeFile('literals.phel', "(str \\( \\) \"a ( b\") ; ( trailing\n");

        $tester = new CommandTester(new BalanceCommand());
        $exit = $tester->execute(['paths' => [$path]]);

        self::assertSame(0, $exit, $tester->getDisplay());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_fails_with_invocation_error_when_no_readable_paths(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new BalanceCommand());
        $exit = $tester->execute(['paths' => [sys_get_temp_dir() . '/phel-balance-does-not-exist']]);

        self::assertSame(BalanceCommand::EXIT_INVOCATION_ERROR, $exit);
    }

    private function writeFile(string $name, string $contents): string
    {
        if ($this->workDir === '') {
            $this->workDir = sys_get_temp_dir() . '/phel-balance-' . uniqid();
            if (!is_dir($this->workDir)) {
                mkdir($this->workDir, 0o777, true);
            }
        }

        $path = $this->workDir . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
