<?php

declare(strict_types=1);

namespace Phel\Commands\Repl;

use PHPUnit\Framework\TestCase;

class ReadlineTest extends TestCase
{
    public function testLineAddedToHistory(): void
    {
        $readLine = new Readline('');

        $readLine->addHistory('first line');
        $readLine->addHistory('second line');

        self::assertSame(
            ['first line', 'second line'],
            $readLine->listHistory()
        );
    }

    public function testHistoryClearResultIsEmpty(): void
    {
        $readLine = new Readline('');

        $readLine->addHistory('first line');
        $readLine->addHistory('second line');

        $readLine->clearHistory();

        self::assertSame([], $readLine->listHistory());
    }
}
