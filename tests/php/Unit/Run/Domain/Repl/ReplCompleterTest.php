<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Phel\Api\Application\ReplCompleter;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\Registry;
use PHPUnit\Framework\TestCase;

final class ReplCompleterTest extends TestCase
{
    private ReplCompleter $completer;

    private Registry $registry;

    public static function tearDownAfterClass(): void
    {
        Registry::getInstance()->clear();
    }

    protected function setUp(): void
    {
        $this->registry = Registry::getInstance();
        $this->registry->clear();

        $phelFnLoader = self::createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->expects(self::once())->method('loadAllPhelFunctions');
        $this->completer = new ReplCompleter($phelFnLoader);
    }

    public function test_empty_input_returns_nothing(): void
    {
        self::assertSame([], $this->completer->complete(''));
    }

    public function test_phel_function_completion(): void
    {
        $fn = $this->createStub(FnInterface::class);
        $this->registry->addDefinition('phel\\core', 'myfn', $fn);

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
