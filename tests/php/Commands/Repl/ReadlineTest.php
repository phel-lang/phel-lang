<?php

declare(strict_types=1);

namespace PhelTest\Commands\Repl;

use Phel\Commands\Repl\Readline;
use PHPUnit\Framework\TestCase;

final class ReadlineTest extends TestCase
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
