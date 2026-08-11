<?php

declare(strict_types=1);

namespace PhelTest\Unit\Balance\Application;

use Gacela\Framework\Gacela;
use Phel\Balance\Application\DelimiterRepairer;
use Phel\Balance\Application\DelimiterScanner;
use Phel\Compiler\CompilerFacade;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class DelimiterRepairerTest extends TestCase
{
    private DelimiterScanner $scanner;

    private DelimiterRepairer $repairer;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        $this->scanner = new DelimiterScanner(new CompilerFacade());
        $this->repairer = new DelimiterRepairer();
    }

    public function test_it_appends_the_closers_to_the_last_non_blank_line(): void
    {
        $code = "(defn f [x]\n  (str x\n\n\n";

        self::assertSame("(defn f [x]\n  (str x))\n", $this->repair($code));
    }

    public function test_it_closes_mixed_nesting_innermost_first(): void
    {
        $code = "(foo [bar {:k 1\n";

        self::assertSame("(foo [bar {:k 1}])\n", $this->repair($code));
    }

    /**
     * Appended inside the comment the closers would be text, and the file would
     * still not parse.
     */
    public function test_it_puts_the_closers_on_a_new_line_after_a_trailing_comment(): void
    {
        $code = "(defn c [x]\n  (str x\n;; tidy later\n";

        self::assertSame("(defn c [x]\n  (str x\n;; tidy later\n))\n", $this->repair($code));
    }

    public function test_it_keeps_a_file_without_a_trailing_newline_without_one(): void
    {
        self::assertSame('(str x)', $this->repair('(str x'));
    }

    public function test_the_repaired_source_scans_as_balanced(): void
    {
        foreach (["(defn f [x]\n  (str x\n", '(foo [bar {:k 1', "(str x\n;; note\n", '(map #(inc %'] as $broken) {
            $repaired = $this->repair($broken);

            self::assertTrue(
                $this->scanner->scan($repaired, 'test.phel')->isBalanced(),
                sprintf('repairing %s should yield balanced source, got %s', $broken, $repaired),
            );
        }
    }

    private function repair(string $code): string
    {
        return $this->repairer->repair($code, $this->scanner->scan($code, 'test.phel'));
    }
}
