<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter;

use Exception;
use Phel\Formatter\Exceptions\CanNotRemoveAtTheTopException;
use Phel\Formatter\Exceptions\ZipperException;
use PHPUnit\Framework\TestCase;

final class ZipperTest extends TestCase
{
    public function testConstruct(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals($tree, $zipper->getNode());
        $this->assertEquals([], $zipper->lefts());
        $this->assertEquals([], $zipper->rights());
        $this->assertTrue($zipper->isTop());
    }

    public function testDownChild(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down();

        $this->assertNotNull($down);
        $this->assertEquals([1, 2], $down->getNode());
    }

    public function testDownLeaf(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down()->down();

        $this->assertNotNull($down);
        $this->assertEquals(1, $down->getNode());
    }

    public function testDownNoChild(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $zipper->down()->down();
    }

    public function testDownOnLeaf(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $zipper->down()->down()->down();
    }

    public function testUp(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->down()->down()->up();

        $this->assertNotNull($down);
        $this->assertEquals([1, 2], $down->getNode());
    }

    public function testUpOnRoot(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $down = $zipper->up();
    }

    public function testLeftOnRoot(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->left();
    }

    public function testLeftOnLeftmost(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->down()->left();
    }

    public function testLeftMost(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $leftMost = $zipper->down()->right()->right()->leftMost();

        $this->assertEquals([1, 2], $leftMost->getNode());
    }

    public function testRightOnRoot(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->right();
    }

    public function testRightOnRightMost(): void
    {
        $this->expectException(ZipperException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->down()->right()->right()->right();
    }

    public function testRightMost(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $rightMost = $zipper->down()->rightMost();

        $this->assertEquals([4, 5], $rightMost->getNode());
    }

    public function testNext(): void
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

    public function testPrev(): void
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

    public function testInsertLeft(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [0, [1, 2], 3, [4, 5]],
            $zipper->down()->insertLeft(0)->root()
        );
    }

    public function testInsertLeftOnTop(): void
    {
        $this->expectException(Exception::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->insertLeft(0);
    }

    public function testInsertRight(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [[1, 2], 0, 3, [4, 5]],
            $zipper->down()->insertRight(0)->root()
        );
    }

    public function testInsertRightOnTop(): void
    {
        $this->expectException(Exception::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->insertRight(0);
    }

    public function testReplace(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [0, 3, [4, 5]],
            $zipper->down()->replace(0)->root()
        );
    }

    public function testInsertChild(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [[0, 1, 2], 3, [4, 5]],
            $zipper->down()->insertChild(0)->root()
        );
    }

    public function testAppendChild(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [[1, 2, 0], 3, [4, 5]],
            $zipper->down()->appendChild(0)->root()
        );
    }

    public function testRemoveInner(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [[1, 2], [4, 5]],
            $zipper->down()->right()->remove(0)->root()
        );
    }

    public function testRemoveLeftMost(): void
    {
        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);

        $this->assertEquals(
            [3, [4, 5]],
            $zipper->down()->remove(0)->root()
        );
    }

    public function testRemoveOnRoot(): void
    {
        $this->expectException(CanNotRemoveAtTheTopException::class);

        $tree = [[1, 2], 3, [4, 5]];
        $zipper = ArrayZipper::createRoot($tree);
        $zipper->remove();
    }
}
