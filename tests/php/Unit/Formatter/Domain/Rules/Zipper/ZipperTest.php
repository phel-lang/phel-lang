<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Domain\Rules\Zipper;

use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use PHPUnit\Framework\TestCase;

final class ZipperTest extends TestCase
{
    public function test_construct(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        self::assertEquals($tree, $zipper->getNode());
        self::assertEquals([], $zipper->lefts());
        self::assertEquals([], $zipper->rights());
        self::assertTrue($zipper->isTop());
    }

    public function test_down_child(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down();

        self::assertEquals([1, 2], $down->getNode());
    }

    public function test_down_leaf(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down()->down();

        self::assertEquals(1, $down->getNode());
    }

    public function test_down_no_child(): void
    {
        $this->expectExceptionObject(ZipperException::cannotGoDownOnNodeWithZeroChildren());

        $tree = [[], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $zipper->down()->down();
    }

    public function test_down_on_leaf(): void
    {
        $this->expectExceptionObject(ZipperException::cannotGoDownOnLeafNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $zipper->down()->down()->down();
    }

    public function test_up(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down()->down()->up();

        self::assertEquals([1, 2], $down->getNode());
    }

    public function test_up_on_root(): void
    {
        $this->expectExceptionObject(ZipperException::cannotGoUpOnRootNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->up();
    }

    public function test_left_on_root(): void
    {
        $this->expectExceptionObject(ZipperException::cannotGoLeftOnRootNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->left();
    }

    public function test_left_on_leftmost(): void
    {
        $this->expectExceptionObject(ZipperException::cannotGoLeftOnTheLeftmostNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->down()->left();
    }

    public function test_left_most(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $leftMost = $zipper->down()->right()->right()->leftMost();

        self::assertEquals([1, 2], $leftMost->getNode());
    }

    public function test_right_on_root(): void
    {
        $this->expectExceptionObject(ZipperException::cannotGoRightOnRootNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->right();
    }

    public function test_right_on_right_most(): void
    {
        $this->expectExceptionObject(ZipperException::cannotGoRightOnLastNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->down()->right()->right()->right();
    }

    public function test_right_most(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $rightMost = $zipper->down()->rightMost();

        self::assertEquals([4, 5], $rightMost->getNode());
    }

    public function test_next(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $next = $zipper->next();
        self::assertEquals([1, 2], $next->getNode());
        $next = $next->next();
        self::assertEquals(1, $next->getNode());
        $next = $next->next();
        self::assertEquals(2, $next->getNode());
        $next = $next->next();
        self::assertEquals(3, $next->getNode());
        $next = $next->next();
        self::assertEquals([4, 5], $next->getNode());
        $next = $next->next();
        self::assertEquals(4, $next->getNode());
        $next = $next->next();
        self::assertEquals(5, $next->getNode());
        $next = $next->next();
        self::assertEquals([[1, 2], 3, [4, 5]], $next->getNode());
        $next = $next->next();
        self::assertTrue($next->isEnd());
        self::assertEquals([[1, 2], 3, [4, 5]], $next->getNode());
    }

    public function test_prev(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $next = $zipper;
        for ($i = 0; $i < 7; $i++) {
            $next = $next->next();
        }
        self::assertTrue(!$next->isEnd());
        self::assertEquals(5, $next->getNode());

        // Now we are in the end and we go back
        $prev = $next->prev();
        self::assertEquals(4, $prev->getNode());
        $prev = $prev->prev();
        self::assertEquals([4, 5], $prev->getNode());
        $prev = $prev->prev();
        self::assertEquals(3, $prev->getNode());
        $prev = $prev->prev();
        self::assertEquals(2, $prev->getNode());
        $prev = $prev->prev();
        self::assertEquals(1, $prev->getNode());
        $prev = $prev->prev();
        self::assertEquals([1, 2], $prev->getNode());
        $prev = $prev->prev();
        self::assertEquals([[1, 2], 3, [4, 5]], $prev->getNode());
    }

    public function test_insert_left(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        self::assertEquals(
            [0, [1, 2], 3, [4, 5]],
            $zipper->down()->insertLeft(0)->root()
        );
    }

    public function test_insert_left_on_top(): void
    {
        $this->expectExceptionObject(ZipperException::cannotInsertLeftOnRootNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->insertLeft(0);
    }

    public function test_insert_right(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        self::assertEquals(
            [[1, 2], 0, 3, [4, 5]],
            $zipper->down()->insertRight(0)->root()
        );
    }

    public function test_insert_right_on_top(): void
    {
        $this->expectExceptionObject(ZipperException::cannotInsertRightOnRootNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->insertRight(0);
    }

    public function test_replace(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        self::assertEquals(
            [0, 3, [4, 5]],
            $zipper->down()->replace(0)->root()
        );
    }

    public function test_insert_child(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        self::assertEquals(
            [[0, 1, 2], 3, [4, 5]],
            $zipper->down()->insertChild(0)->root()
        );
    }

    public function test_append_child(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        self::assertEquals(
            [[1, 2, 0], 3, [4, 5]],
            $zipper->down()->appendChild(0)->root()
        );
    }

    public function test_remove_inner(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        self::assertEquals(
            [[1, 2], [4, 5]],
            $zipper->down()->right()->remove()->root()
        );
    }

    public function test_remove_left_most(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        self::assertEquals(
            [3, [4, 5]],
            $zipper->down()->remove()->root()
        );
    }

    public function test_remove_on_root(): void
    {
        $this->expectExceptionObject(ZipperException::cannotRemoveOnRootNode());

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->remove();
    }
}
