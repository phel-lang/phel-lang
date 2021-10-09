<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Domain\Rules\Zipper;

use Exception;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use PHPUnit\Framework\TestCase;

final class ZipperTest extends TestCase
{
    public function test_construct(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals($tree, $zipper->getNode());
        $this->assertEquals([], $zipper->lefts());
        $this->assertEquals([], $zipper->rights());
        $this->assertTrue($zipper->isTop());
    }

    public function test_down_child(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down();

        $this->assertNotNull($down);
        $this->assertEquals([1, 2], $down->getNode());
    }

    public function test_down_leaf(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down()->down();

        $this->assertNotNull($down);
        $this->assertEquals(1, $down->getNode());
    }

    public function test_down_no_child(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $zipper->down()->down();
    }

    public function test_down_on_leaf(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $zipper->down()->down()->down();
    }

    public function test_up(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down()->down()->up();

        $this->assertNotNull($down);
        $this->assertEquals([1, 2], $down->getNode());
    }

    public function test_up_on_root(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->up();
    }

    public function test_left_on_root(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->left();
    }

    public function test_left_on_leftmost(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->down()->left();
    }

    public function test_left_most(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $leftMost = $zipper->down()->right()->right()->leftMost();

        $this->assertEquals([1, 2], $leftMost->getNode());
    }

    public function test_right_on_root(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->right();
    }

    public function test_right_on_right_most(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->down()->right()->right()->right();
    }

    public function test_right_most(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $rightMost = $zipper->down()->rightMost();

        $this->assertEquals([4, 5], $rightMost->getNode());
    }

    public function test_next(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $next = $zipper->next();
        $this->assertEquals([1, 2], $next->getNode());
        $next = $next->next();
        $this->assertEquals(1, $next->getNode());
        $next = $next->next();
        $this->assertEquals(2, $next->getNode());
        $next = $next->next();
        $this->assertEquals(3, $next->getNode());
        $next = $next->next();
        $this->assertEquals([4, 5], $next->getNode());
        $next = $next->next();
        $this->assertEquals(4, $next->getNode());
        $next = $next->next();
        $this->assertEquals(5, $next->getNode());
        $next = $next->next();
        $this->assertEquals([[1, 2], 3, [4, 5]], $next->getNode());
        $next = $next->next();
        $this->assertTrue($next->isEnd());
        $this->assertEquals([[1, 2], 3, [4, 5]], $next->getNode());
    }

    public function test_prev(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $next = $zipper;
        for ($i = 0; $i < 7; $i++) {
            $next = $next->next();
        }
        $this->assertTrue(!$next->isEnd());
        $this->assertEquals(5, $next->getNode());

        // Now we are and the and and go back
        $prev = $next->prev();
        $this->assertEquals(4, $prev->getNode());
        $prev = $prev->prev();
        $this->assertEquals([4, 5], $prev->getNode());
        $prev = $prev->prev();
        $this->assertEquals(3, $prev->getNode());
        $prev = $prev->prev();
        $this->assertEquals(2, $prev->getNode());
        $prev = $prev->prev();
        $this->assertEquals(1, $prev->getNode());
        $prev = $prev->prev();
        $this->assertEquals([1, 2], $prev->getNode());
        $prev = $prev->prev();
        $this->assertEquals([[1, 2], 3, [4, 5]], $prev->getNode());
    }

    public function test_insert_left(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [0, [1, 2], 3, [4, 5]],
            $zipper->down()->insertLeft(0)->root()
        );
    }

    public function test_insert_left_on_top(): void
    {
        $this->expectException(Exception::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->insertLeft(0);
    }

    public function test_insert_right(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [[1, 2], 0, 3, [4, 5]],
            $zipper->down()->insertRight(0)->root()
        );
    }

    public function test_insert_right_on_top(): void
    {
        $this->expectException(Exception::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->insertRight(0);
    }

    public function test_replace(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [0, 3, [4, 5]],
            $zipper->down()->replace(0)->root()
        );
    }

    public function test_insert_child(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [[0, 1, 2], 3, [4, 5]],
            $zipper->down()->insertChild(0)->root()
        );
    }

    public function test_append_child(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [[1, 2, 0], 3, [4, 5]],
            $zipper->down()->appendChild(0)->root()
        );
    }

    public function test_remove_inner(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [[1, 2], [4, 5]],
            $zipper->down()->right()->remove(0)->root()
        );
    }

    public function test_remove_left_most(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [3, [4, 5]],
            $zipper->down()->remove()->root()
        );
    }

    public function test_remove_on_root(): void
    {
        $this->expectExceptionObject(ZipperException::cannotRemoveOnRootNode());

        $tree = [[1, 2], [3], [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->remove();
    }
}
