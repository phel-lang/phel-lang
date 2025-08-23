<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Phel;
use Phel\Api\Application\ReplCompleter;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Lang\FnInterface;
use PHPUnit\Framework\TestCase;

final class ReplCompleterTest extends TestCase
{
    private ReplCompleter $completer;

    public static function tearDownAfterClass(): void
    {
        Phel::clear();
    }

    protected function setUp(): void
    {
        Phel::clear();

        $phelFnLoader = self::createStub(PhelFnLoaderInterface::class);
        $this->completer = new ReplCompleter($phelFnLoader);
    }

    public function test_empty_input_returns_nothing(): void
    {
        self::assertSame([], $this->completer->complete(''));
    }

    public function test_phel_function_completion(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\core', 'myfn', $fn);

        self::assertSame(['myfn'], $this->completer->complete('my'));
    }

    public function test_php_function_completion(): void
    {
        $matches = $this->completer->complete('php/strl');

        self::assertContains('php/strlen', $matches);
    }

    public function test_php_class_completion(): void
    {
        $matches = $this->completer->complete('php/DateT');

        self::assertContains('php/DateTime', $matches);
    }
}
