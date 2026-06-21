<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\LiteralStringFolder;
use PHPUnit\Framework\TestCase;

final class LiteralStringFolderTest extends TestCase
{
    private LiteralStringFolder $folder;

    protected function setUp(): void
    {
        $this->folder = new LiteralStringFolder();
    }

    public function test_supports_only_equality_fns(): void
    {
        self::assertTrue($this->folder->supports('='));
        self::assertTrue($this->folder->supports('not='));
        self::assertFalse($this->folder->supports('<'));
        self::assertFalse($this->folder->supports('str'));
    }

    public function test_equal_strings_fold_to_true(): void
    {
        self::assertTrue($this->folder->fold('=', $this->literals('a', 'a')));
    }

    public function test_unequal_strings_fold_to_false(): void
    {
        self::assertFalse($this->folder->fold('=', $this->literals('a', 'b')));
    }

    public function test_not_equal_negates(): void
    {
        self::assertTrue($this->folder->fold('not=', $this->literals('a', 'b')));
        self::assertFalse($this->folder->fold('not=', $this->literals('a', 'a')));
    }

    public function test_n_ary_equality(): void
    {
        self::assertTrue($this->folder->fold('=', $this->literals('x', 'x', 'x')));
        self::assertFalse($this->folder->fold('=', $this->literals('x', 'x', 'y')));
    }

    public function test_single_arg_is_true(): void
    {
        self::assertTrue($this->folder->fold('=', $this->literals('a')));
    }

    public function test_zero_args_stay_unfolded(): void
    {
        self::assertNull($this->folder->fold('=', []));
    }

    public function test_mixed_type_operands_stay_unfolded(): void
    {
        $args = [
            new LiteralNode(NodeEnvironment::empty(), 'a'),
            new LiteralNode(NodeEnvironment::empty(), 1),
        ];

        self::assertNull($this->folder->fold('=', $args));
        self::assertNull($this->folder->fold('not=', $args));
    }

    public function test_non_literal_args_stay_unfolded(): void
    {
        $args = [
            new LiteralNode(NodeEnvironment::empty(), 'a'),
            new class(NodeEnvironment::empty()) extends AbstractNode {},
        ];

        self::assertNull($this->folder->fold('=', $args));
    }

    /**
     * @return list<LiteralNode>
     */
    private function literals(string ...$values): array
    {
        $literals = [];
        foreach ($values as $value) {
            $literals[] = new LiteralNode(NodeEnvironment::empty(), $value);
        }

        return $literals;
    }
}
